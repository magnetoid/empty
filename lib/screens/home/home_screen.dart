import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../providers/book_provider.dart';
import '../../providers/auth_provider.dart';
import '../../utils/app_theme.dart';
import '../../widgets/book_card.dart';
import '../../widgets/search_bar.dart';
import '../../widgets/section_header.dart';
import 'book_detail_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  final TextEditingController _searchController = TextEditingController();
  String _searchQuery = '';

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

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
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Welcome message
                  Consumer<AuthProvider>(
                    builder: (context, authProvider, child) {
                      return Row(
                        children: [
                          CircleAvatar(
                            radius: 25,
                            backgroundColor: Colors.white,
                            child: Text(
                              authProvider.user?.firstName.substring(0, 1).toUpperCase() ?? 'U',
                              style: const TextStyle(
                                fontSize: 20,
                                fontWeight: FontWeight.bold,
                                color: AppTheme.primaryColor,
                              ),
                            ),
                          ),
                          const SizedBox(width: 15),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Text(
                                  'Welcome back!',
                                  style: TextStyle(
                                    color: Colors.white70,
                                    fontSize: 14,
                                  ),
                                ),
                                Text(
                                  authProvider.user?.displayName ?? 'User',
                                  style: const TextStyle(
                                    color: Colors.white,
                                    fontSize: 20,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          IconButton(
                            onPressed: () {
                              // TODO: Add notification functionality
                            },
                            icon: const Icon(
                              Icons.notifications_outlined,
                              color: Colors.white,
                            ),
                          ),
                        ],
                      );
                    },
                  ),
                  const SizedBox(height: 20),
                  
                  // Search bar
                  CustomSearchBar(
                    controller: _searchController,
                    onChanged: (value) {
                      setState(() {
                        _searchQuery = value;
                      });
                    },
                  ),
                ],
              ),
            ),

            // Content
            Expanded(
              child: Consumer<BookProvider>(
                builder: (context, bookProvider, child) {
                  if (bookProvider.isLoading) {
                    return const Center(
                      child: CircularProgressIndicator(),
                    );
                  }

                  final searchResults = bookProvider.searchBooks(_searchQuery);
                  final recentlyRead = bookProvider.getRecentlyReadBooks();
                  final downloadedBooks = bookProvider.getDownloadedBooks();

                  return SingleChildScrollView(
                    padding: const EdgeInsets.all(20),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        // Recently Read
                        if (recentlyRead.isNotEmpty) ...[
                          SectionHeader(
                            title: 'Recently Read',
                            onSeeAll: () {
                              // TODO: Navigate to recently read books
                            },
                          ),
                          const SizedBox(height: 15),
                          SizedBox(
                            height: 200,
                            child: ListView.builder(
                              scrollDirection: Axis.horizontal,
                              itemCount: recentlyRead.length,
                              itemBuilder: (context, index) {
                                final book = recentlyRead[index];
                                return Padding(
                                  padding: EdgeInsets.only(
                                    right: index < recentlyRead.length - 1 ? 15 : 0,
                                  ),
                                  child: BookCard(
                                    book: book,
                                    onTap: () {
                                      Navigator.push(
                                        context,
                                        MaterialPageRoute(
                                          builder: (context) => BookDetailScreen(book: book),
                                        ),
                                      );
                                    },
                                  ),
                                );
                              },
                            ),
                          ),
                          const SizedBox(height: 30),
                        ],

                        // Downloaded Books
                        if (downloadedBooks.isNotEmpty) ...[
                          SectionHeader(
                            title: 'Downloaded Books',
                            onSeeAll: () {
                              // TODO: Navigate to downloaded books
                            },
                          ),
                          const SizedBox(height: 15),
                          SizedBox(
                            height: 200,
                            child: ListView.builder(
                              scrollDirection: Axis.horizontal,
                              itemCount: downloadedBooks.length,
                              itemBuilder: (context, index) {
                                final book = downloadedBooks[index];
                                return Padding(
                                  padding: EdgeInsets.only(
                                    right: index < downloadedBooks.length - 1 ? 15 : 0,
                                  ),
                                  child: BookCard(
                                    book: book,
                                    onTap: () {
                                      Navigator.push(
                                        context,
                                        MaterialPageRoute(
                                          builder: (context) => BookDetailScreen(book: book),
                                        ),
                                      );
                                    },
                                  ),
                                );
                              },
                            ),
                          ),
                          const SizedBox(height: 30),
                        ],

                        // All Books or Search Results
                        SectionHeader(
                          title: _searchQuery.isEmpty ? 'All Books' : 'Search Results',
                          onSeeAll: _searchQuery.isEmpty ? () {
                            // TODO: Navigate to all books
                          } : null,
                        ),
                        const SizedBox(height: 15),
                        
                        if (searchResults.isEmpty && _searchQuery.isNotEmpty)
                          const Center(
                            child: Text(
                              'No books found matching your search.',
                              style: TextStyle(
                                color: Colors.grey,
                                fontSize: 16,
                              ),
                            ),
                          )
                        else
                          GridView.builder(
                            shrinkWrap: true,
                            physics: const NeverScrollableScrollPhysics(),
                            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                              crossAxisCount: 2,
                              childAspectRatio: 0.7,
                              crossAxisSpacing: 15,
                              mainAxisSpacing: 15,
                            ),
                            itemCount: searchResults.length,
                            itemBuilder: (context, index) {
                              final book = searchResults[index];
                              return BookCard(
                                book: book,
                                onTap: () {
                                  Navigator.push(
                                    context,
                                    MaterialPageRoute(
                                      builder: (context) => BookDetailScreen(book: book),
                                    ),
                                  );
                                },
                              );
                            },
                          ),
                      ],
                    ),
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}