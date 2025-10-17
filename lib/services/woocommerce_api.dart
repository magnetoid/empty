import 'dart:convert';
import 'dart:io';
import 'package:dio/dio.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:path_provider/path_provider.dart';
import 'package:permission_handler/permission_handler.dart';
import '../models/book.dart';
import '../models/user.dart';

class WooCommerceAPI {
  static final WooCommerceAPI _instance = WooCommerceAPI._internal();
  factory WooCommerceAPI() => _instance;
  WooCommerceAPI._internal();

  late Dio _dio;
  String? _baseUrl;
  String? _consumerKey;
  String? _consumerSecret;
  String? _authToken;

  Future<void> initialize({
    required String baseUrl,
    required String consumerKey,
    required String consumerSecret,
  }) async {
    _baseUrl = baseUrl;
    _consumerKey = consumerKey;
    _consumerSecret = consumerSecret;

    _dio = Dio(BaseOptions(
      baseUrl: '$baseUrl/wp-json/wc/v3',
      timeout: const Duration(seconds: 30),
      headers: {
        'Content-Type': 'application/json',
      },
    ));

    // Set up basic auth for WooCommerce API
    final credentials = base64Encode(utf8.encode('$consumerKey:$consumerSecret'));
    _dio.options.headers['Authorization'] = 'Basic $credentials';

    // Load saved auth token
    final prefs = await SharedPreferences.getInstance();
    _authToken = prefs.getString('auth_token');
  }

  Future<Map<String, dynamic>> authenticateUser(String email, String password) async {
    try {
      final response = await Dio().post(
        '$_baseUrl/wp-json/jwt-auth/v1/token',
        data: {
          'username': email,
          'password': password,
        },
      );

      if (response.statusCode == 200 && response.data['token'] != null) {
        _authToken = response.data['token'];
        
        // Save token
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString('auth_token', _authToken!);

        // Get user details
        final userResponse = await Dio().get(
          '$_baseUrl/wp-json/wp/v2/users/me',
          options: Options(
            headers: {
              'Authorization': 'Bearer $_authToken',
            },
          ),
        );

        final user = User.fromJson(userResponse.data);
        
        return {
          'success': true,
          'user': user,
          'token': _authToken,
        };
      }

      return {
        'success': false,
        'message': 'Authentication failed',
      };
    } catch (e) {
      return {
        'success': false,
        'message': 'Authentication error: ${e.toString()}',
      };
    }
  }

  Future<Map<String, dynamic>> registerUser({
    required String email,
    required String password,
    required String firstName,
    required String lastName,
  }) async {
    try {
      final response = await Dio().post(
        '$_baseUrl/wp-json/wp/v2/users',
        data: {
          'username': email,
          'email': email,
          'password': password,
          'first_name': firstName,
          'last_name': lastName,
        },
        options: Options(
          headers: {
            'Authorization': 'Basic ${base64Encode(utf8.encode('$_consumerKey:$_consumerSecret'))}',
          },
        ),
      );

      if (response.statusCode == 201) {
        // Auto-login after registration
        return await authenticateUser(email, password);
      }

      return {
        'success': false,
        'message': 'Registration failed',
      };
    } catch (e) {
      return {
        'success': false,
        'message': 'Registration error: ${e.toString()}',
      };
    }
  }

  Future<Map<String, dynamic>> getBooks() async {
    try {
      final response = await _dio.get('/products', queryParameters: {
        'category': 'ebooks',
        'per_page': 50,
        'status': 'publish',
      });

      final books = (response.data as List)
          .map((json) => Book.fromJson(json))
          .toList();

      return {
        'success': true,
        'books': books,
      };
    } catch (e) {
      return {
        'success': false,
        'message': 'Failed to fetch books: ${e.toString()}',
      };
    }
  }

  Future<Map<String, dynamic>> getPurchasedBooks() async {
    try {
      if (_authToken == null) {
        return {
          'success': false,
          'message': 'Not authenticated',
        };
      }

      // Get user's orders
      final ordersResponse = await Dio().get(
        '$_baseUrl/wp-json/wc/v3/orders',
        options: Options(
          headers: {
            'Authorization': 'Bearer $_authToken',
          },
        ),
        queryParameters: {
          'status': 'completed',
          'per_page': 50,
        },
      );

      final Set<int> purchasedBookIds = {};
      final List<Book> purchasedBooks = [];

      // Extract book IDs from completed orders
      for (var order in ordersResponse.data) {
        for (var item in order['line_items']) {
          if (item['meta_data']?.any((meta) => meta['key'] == '_is_ebook') == true) {
            purchasedBookIds.add(item['product_id']);
          }
        }
      }

      // Fetch book details for purchased books
      for (int bookId in purchasedBookIds) {
        try {
          final bookResponse = await _dio.get('/products/$bookId');
          final book = Book.fromJson(bookResponse.data);
          purchasedBooks.add(book.copyWith(isPurchased: true));
        } catch (e) {
          print('Error fetching book $bookId: $e');
        }
      }

      return {
        'success': true,
        'books': purchasedBooks,
      };
    } catch (e) {
      return {
        'success': false,
        'message': 'Failed to fetch purchased books: ${e.toString()}',
      };
    }
  }

  Future<Map<String, dynamic>> downloadBook(int bookId) async {
    try {
      if (_authToken == null) {
        return {
          'success': false,
          'message': 'Not authenticated',
        };
      }

      // Get book details
      final response = await _dio.get('/products/$bookId');
      final book = Book.fromJson(response.data);
      
      if (book.downloadUrl == null) {
        return {
          'success': false,
          'message': 'Download URL not found',
        };
      }

      // Request storage permission
      final permission = await Permission.storage.request();
      if (!permission.isGranted) {
        return {
          'success': false,
          'message': 'Storage permission denied',
        };
      }

      // Create downloads directory
      final directory = await getApplicationDocumentsDirectory();
      final downloadsDir = Directory('${directory.path}/downloads');
      if (!await downloadsDir.exists()) {
        await downloadsDir.create(recursive: true);
      }

      // Determine file extension
      final fileExtension = book.fileType == BookFileType.pdf ? '.pdf' : '.epub';
      final fileName = 'book_${bookId}$fileExtension';
      final filePath = '${downloadsDir.path}/$fileName';

      // Download the file
      await Dio().download(
        book.downloadUrl!,
        filePath,
        options: Options(
          headers: {
            'Authorization': 'Bearer $_authToken',
          },
        ),
      );

      return {
        'success': true,
        'file_path': filePath,
      };
    } catch (e) {
      return {
        'success': false,
        'message': 'Download failed: ${e.toString()}',
      };
    }
  }

  Future<void> logout() async {
    _authToken = null;
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('auth_token');
  }
}