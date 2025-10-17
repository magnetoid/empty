import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../models/user.dart';
import '../services/woocommerce_api.dart';

class AuthProvider with ChangeNotifier {
  User? _user;
  bool _isLoading = false;
  final WooCommerceAPI _api = WooCommerceAPI();

  User? get user => _user;
  bool get isAuthenticated => _user != null;
  bool get isLoading => _isLoading;

  AuthProvider() {
    _loadUser();
  }

  Future<void> _loadUser() async {
    _isLoading = true;
    notifyListeners();

    try {
      final prefs = await SharedPreferences.getInstance();
      final userJson = prefs.getString('user_data');
      
      if (userJson != null) {
        final userData = jsonDecode(userJson);
        _user = User.fromJson(userData);
      }
    } catch (e) {
      print('Error loading user: $e');
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<bool> login(String email, String password) async {
    _isLoading = true;
    notifyListeners();

    try {
      final result = await _api.authenticateUser(email, password);
      
      if (result['success'] == true) {
        _user = result['user'];
        
        // Save user data
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString('user_data', jsonEncode(_user!.toJson()));
        
        return true;
      }
      return false;
    } catch (e) {
      print('Login error: $e');
      return false;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<bool> register({
    required String email,
    required String password,
    required String firstName,
    required String lastName,
  }) async {
    _isLoading = true;
    notifyListeners();

    try {
      final result = await _api.registerUser(
        email: email,
        password: password,
        firstName: firstName,
        lastName: lastName,
      );
      
      if (result['success'] == true) {
        _user = result['user'];
        
        // Save user data
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString('user_data', jsonEncode(_user!.toJson()));
        
        return true;
      }
      return false;
    } catch (e) {
      print('Registration error: $e');
      return false;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<void> logout() async {
    _user = null;
    await _api.logout();
    
    // Clear user data
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('user_data');
    
    notifyListeners();
  }
}