import 'package:hive/hive.dart';

part 'user.g.dart';

@HiveType(typeId: 3)
class User {
  @HiveField(0)
  final int id;
  
  @HiveField(1)
  final String email;
  
  @HiveField(2)
  final String username;
  
  @HiveField(3)
  final String firstName;
  
  @HiveField(4)
  final String lastName;
  
  @HiveField(5)
  final String? avatarUrl;

  User({
    required this.id,
    required this.email,
    required this.username,
    required this.firstName,
    required this.lastName,
    this.avatarUrl,
  });

  factory User.fromJson(Map<String, dynamic> json) {
    return User(
      id: json['id'] ?? 0,
      email: json['email'] ?? '',
      username: json['username'] ?? '',
      firstName: json['first_name'] ?? '',
      lastName: json['last_name'] ?? '',
      avatarUrl: json['avatar_url'],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'email': email,
      'username': username,
      'first_name': firstName,
      'last_name': lastName,
      'avatar_url': avatarUrl,
    };
  }

  String get fullName {
    return '$firstName $lastName'.trim();
  }

  String get displayName {
    if (fullName.isNotEmpty) {
      return fullName;
    }
    return username;
  }
}