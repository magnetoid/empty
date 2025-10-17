import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../providers/auth_provider.dart';
import '../../providers/theme_provider.dart';
import '../../utils/app_theme.dart';

class SettingsScreen extends StatelessWidget {
  const SettingsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Column(
          children: [
            // Header
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                gradient: AppTheme.primaryGradient,
                borderRadius: const BorderRadius.only(
                  bottomLeft: Radius.circular(30),
                  bottomRight: Radius.circular(30),
                ),
              ),
              child: const Text(
                'Settings',
                style: TextStyle(
                  fontSize: 28,
                  fontWeight: FontWeight.bold,
                  color: Colors.white,
                ),
              ),
            ),

            // Content
            Expanded(
              child: ListView(
                padding: const EdgeInsets.all(20),
                children: [
                  // Profile Section
                  _buildSection(
                    title: 'Profile',
                    children: [
                      Consumer<AuthProvider>(
                        builder: (context, authProvider, child) {
                          return ListTile(
                            leading: CircleAvatar(
                              backgroundColor: AppTheme.primaryColor,
                              child: Text(
                                authProvider.user?.firstName.substring(0, 1).toUpperCase() ?? 'U',
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                            ),
                            title: Text(
                              authProvider.user?.displayName ?? 'User',
                              style: const TextStyle(
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                            subtitle: Text(
                              authProvider.user?.email ?? '',
                              style: const TextStyle(
                                color: Colors.grey,
                              ),
                            ),
                            trailing: const Icon(Icons.arrow_forward_ios),
                            onTap: () {
                              // TODO: Navigate to profile edit
                            },
                          );
                        },
                      ),
                    ],
                  ),

                  const SizedBox(height: 20),

                  // Appearance Section
                  _buildSection(
                    title: 'Appearance',
                    children: [
                      Consumer<ThemeProvider>(
                        builder: (context, themeProvider, child) {
                          return ListTile(
                            leading: const Icon(Icons.dark_mode),
                            title: const Text('Dark Mode'),
                            subtitle: Text(
                              themeProvider.isDarkMode ? 'Enabled' : 'Disabled',
                            ),
                            trailing: Switch(
                              value: themeProvider.isDarkMode,
                              onChanged: (value) {
                                themeProvider.toggleTheme();
                              },
                            ),
                          );
                        },
                      ),
                    ],
                  ),

                  const SizedBox(height: 20),

                  // Reading Section
                  _buildSection(
                    title: 'Reading',
                    children: [
                      ListTile(
                        leading: const Icon(Icons.font_download),
                        title: const Text('Font Size'),
                        subtitle: const Text('Medium'),
                        trailing: const Icon(Icons.arrow_forward_ios),
                        onTap: () {
                          // TODO: Show font size options
                        },
                      ),
                      ListTile(
                        leading: const Icon(Icons.brightness_6),
                        title: const Text('Reading Theme'),
                        subtitle: const Text('Default'),
                        trailing: const Icon(Icons.arrow_forward_ios),
                        onTap: () {
                          // TODO: Show reading theme options
                        },
                      ),
                      ListTile(
                        leading: const Icon(Icons.auto_stories),
                        title: const Text('Auto-scroll'),
                        subtitle: const Text('Disabled'),
                        trailing: Switch(
                          value: false,
                          onChanged: (value) {
                            // TODO: Implement auto-scroll
                          },
                        ),
                      ),
                    ],
                  ),

                  const SizedBox(height: 20),

                  // Storage Section
                  _buildSection(
                    title: 'Storage',
                    children: [
                      ListTile(
                        leading: const Icon(Icons.download),
                        title: const Text('Downloaded Books'),
                        subtitle: const Text('Manage offline content'),
                        trailing: const Icon(Icons.arrow_forward_ios),
                        onTap: () {
                          // TODO: Navigate to downloaded books management
                        },
                      ),
                      ListTile(
                        leading: const Icon(Icons.storage),
                        title: const Text('Storage Usage'),
                        subtitle: const Text('2.5 GB used'),
                        trailing: const Icon(Icons.arrow_forward_ios),
                        onTap: () {
                          // TODO: Show storage details
                        },
                      ),
                    ],
                  ),

                  const SizedBox(height: 20),

                  // Account Section
                  _buildSection(
                    title: 'Account',
                    children: [
                      ListTile(
                        leading: const Icon(Icons.logout),
                        title: const Text('Logout'),
                        onTap: () {
                          _showLogoutDialog(context);
                        },
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSection({
    required String title,
    required List<Widget> children,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.only(left: 16, bottom: 8),
          child: Text(
            title,
            style: const TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w600,
              color: Colors.grey,
            ),
          ),
        ),
        Card(
          child: Column(
            children: children,
          ),
        ),
      ],
    );
  }

  void _showLogoutDialog(BuildContext context) {
    showDialog(
      context: context,
      builder: (BuildContext context) {
        return AlertDialog(
          title: const Text('Logout'),
          content: const Text('Are you sure you want to logout?'),
          actions: [
            TextButton(
              onPressed: () {
                Navigator.of(context).pop();
              },
              child: const Text('Cancel'),
            ),
            TextButton(
              onPressed: () async {
                Navigator.of(context).pop();
                final authProvider = Provider.of<AuthProvider>(context, listen: false);
                await authProvider.logout();
              },
              child: const Text(
                'Logout',
                style: TextStyle(color: Colors.red),
              ),
            ),
          ],
        );
      },
    );
  }
}