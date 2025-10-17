import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'dart:convert';
import '../models/book.dart';
import '../services/woocommerce_api.dart';

class BookProvider with ChangeNotifier {
  List<Book> _books = [];
  List<Book> _purchasedBooks = [];
  bool _isLoading = false;
  final WooCommerceAPI _api = WooCommerceAPI();

  List<Book> get books => _books;
  List<Book> get purchasedBooks => _purchasedBooks;
  bool get isLoading => _isLoading;

  BookProvider() {
    _loadBooks();
  }

  Future<void> _loadBooks() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final booksJson = prefs.getString('books');
      final purchasedBooksJson = prefs.getString('purchased_books');
      
      if (booksJson != null) {
        final booksData = jsonDecode(booksJson) as List;
        _books = booksData.map((json) => Book.fromJson(json)).toList();
      }
      
      if (purchasedBooksJson != null) {
        final purchasedBooksData = jsonDecode(purchasedBooksJson) as List;
        _purchasedBooks = purchasedBooksData.map((json) => Book.fromJson(json)).toList();
      }
    } catch (e) {
      print('Error loading books: $e');
    }
  }

  Future<void> fetchBooks() async {
    _isLoading = true;
    notifyListeners();

    try {
      final result = await _api.getBooks();
      
      if (result['success'] == true) {
        _books = result['books'];
        
        // Save books
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString('books', jsonEncode(_books.map((b) => b.toJson()).toList()));
      }
    } catch (e) {
      print('Error fetching books: $e');
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<void> fetchPurchasedBooks() async {
    _isLoading = true;
    notifyListeners();

    try {
      final result = await _api.getPurchasedBooks();
      
      if (result['success'] == true) {
        _purchasedBooks = result['books'];
        
        // Save purchased books
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString('purchased_books', jsonEncode(_purchasedBooks.map((b) => b.toJson()).toList()));
      }
    } catch (e) {
      print('Error fetching purchased books: $e');
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<bool> downloadBook(Book book) async {
    try {
      final result = await _api.downloadBook(book.id);
      
      if (result['success'] == true) {
        final updatedBook = book.copyWith(
          isDownloaded: true,
          localPath: result['file_path'],
        );
        
        final index = _purchasedBooks.indexWhere((b) => b.id == book.id);
        if (index != -1) {
          _purchasedBooks[index] = updatedBook;
          
          // Save updated purchased books
          final prefs = await SharedPreferences.getInstance();
          await prefs.setString('purchased_books', jsonEncode(_purchasedBooks.map((b) => b.toJson()).toList()));
          
          notifyListeners();
        }
        
        return true;
      }
      return false;
    } catch (e) {
      print('Error downloading book: $e');
      return false;
    }
  }

  Future<void> updateReadingProgress(int bookId, double progress) async {
    try {
      final index = _purchasedBooks.indexWhere((b) => b.id == bookId);
      if (index != -1) {
        _purchasedBooks[index] = _purchasedBooks[index].copyWith(
          readingProgress: progress,
          lastRead: DateTime.now(),
        );
        
        // Save updated purchased books
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString('purchased_books', jsonEncode(_purchasedBooks.map((b) => b.toJson()).toList()));
        
        notifyListeners();
      }
    } catch (e) {
      print('Error updating reading progress: $e');
    }
  }

  Book? getBookById(int id) {
    return [..._books, ..._purchasedBooks].firstWhere(
      (book) => book.id == id,
      orElse: () => throw Exception('Book not found'),
    );
  }

  List<Book> searchBooks(String query) {
    if (query.isEmpty) return _books;
    
    final searchTerm = query.toLowerCase();
    return _books.where((book) {
      return book.name.toLowerCase().contains(searchTerm) ||
             book.description.toLowerCase().contains(searchTerm) ||
             (book.author?.toLowerCase().contains(searchTerm) ?? false);
    }).toList();
  }

  List<Book> getRecentlyReadBooks() {
    return _purchasedBooks
        .where((book) => book.lastRead != null)
        .toList()
      ..sort((a, b) => b.lastRead!.compareTo(a.lastRead!));
  }

  List<Book> getDownloadedBooks() {
    return _purchasedBooks.where((book) => book.isDownloaded).toList();
  }
}