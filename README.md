# Mobile Ebook Reader

A Flutter-based mobile ebook reading application that integrates with WooCommerce for selling and managing ebooks and PDFs.

## Features

### 📚 Core Reading Features
- **PDF Reader**: Full-featured PDF viewer with zoom, scroll, and page navigation
- **EPUB Support**: Basic EPUB reader (coming soon)
- **Offline Reading**: Download books for offline access
- **Reading Progress**: Track reading progress and resume where you left off
- **Bookmarks**: Save your favorite pages and sections

### 🛒 E-commerce Integration
- **WooCommerce API**: Seamless integration with WooCommerce stores
- **User Authentication**: Secure login and registration
- **Purchase Verification**: Automatic verification of purchased books
- **Digital Downloads**: Secure download system for purchased content

### 🎨 User Interface
- **Material Design**: Modern, responsive UI following Material Design principles
- **Dark/Light Theme**: Toggle between dark and light themes
- **Search Functionality**: Search through your library and available books
- **Grid/List Views**: Multiple viewing options for your book collection

### 📱 Mobile Features
- **Cross-Platform**: Works on both Android and iOS
- **Responsive Design**: Optimized for different screen sizes
- **Gesture Support**: Intuitive touch gestures for navigation
- **Background Processing**: Download books in the background

## Getting Started

### Prerequisites
- Flutter SDK (3.0.0 or higher)
- Dart SDK (3.0.0 or higher)
- Android Studio / Xcode for mobile development
- WooCommerce store with API access

### Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd mobile-ebook-reader
   ```

2. **Install dependencies**
   ```bash
   flutter pub get
   ```

3. **Generate Hive models**
   ```bash
   flutter packages pub run build_runner build
   ```

4. **Configure WooCommerce**
   - Set up your WooCommerce store
   - Create API keys (Consumer Key and Consumer Secret)
   - Configure the API service in `lib/services/woocommerce_api.dart`

5. **Run the app**
   ```bash
   flutter run
   ```

### WooCommerce Setup

1. **Install WooCommerce** on your WordPress site
2. **Enable REST API** in WooCommerce settings
3. **Create API Keys**:
   - Go to WooCommerce → Settings → Advanced → REST API
   - Click "Add Key"
   - Set permissions to "Read/Write"
   - Copy the Consumer Key and Consumer Secret

4. **Configure Products**:
   - Create products with category "ebooks"
   - Add custom fields for book metadata:
     - `_download_url`: Direct download link
     - `_file_type`: pdf or epub
     - `_author`: Book author
     - `_publisher`: Publisher name
     - `_pages`: Number of pages
     - `_isbn`: ISBN number
     - `_published_date`: Publication date

## Project Structure

```
lib/
├── main.dart                 # App entry point
├── models/                   # Data models
│   ├── book.dart            # Book model with Hive annotations
│   └── user.dart            # User model
├── providers/               # State management
│   ├── auth_provider.dart   # Authentication state
│   ├── book_provider.dart   # Book management state
│   └── theme_provider.dart  # Theme state
├── services/                # API services
│   └── woocommerce_api.dart # WooCommerce integration
├── screens/                 # UI screens
│   ├── auth/               # Authentication screens
│   ├── home/               # Home and book detail screens
│   ├── library/            # Library management
│   ├── reader/             # Reading interface
│   └── settings/           # Settings and preferences
├── widgets/                 # Reusable UI components
└── utils/                   # Utilities and themes
    └── app_theme.dart      # App theming
```

## Key Dependencies

- **State Management**: Provider
- **Local Storage**: Hive, SharedPreferences
- **HTTP Client**: Dio
- **PDF Reader**: Syncfusion Flutter PDF Viewer
- **Image Caching**: Cached Network Image
- **UI Components**: Material Design widgets
- **File Management**: Path Provider, Permission Handler

## Configuration

### Android Configuration
- Minimum SDK: 21 (Android 5.0)
- Target SDK: Latest
- Permissions: Internet, Storage, Network State

### iOS Configuration
- Minimum iOS: 11.0
- Permissions: Network access, File sharing

## Features in Detail

### Authentication
- JWT-based authentication with WooCommerce
- Secure token storage
- Automatic session management
- User profile management

### Book Management
- Browse available books from WooCommerce
- Purchase verification
- Download management
- Offline reading support
- Reading progress tracking

### Reading Experience
- Smooth PDF rendering
- Zoom and pan functionality
- Page navigation
- Reading progress indicators
- Bookmark system

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Support

For support and questions:
- Create an issue in the repository
- Check the documentation
- Review the WooCommerce API documentation

## Roadmap

- [ ] EPUB reader implementation
- [ ] Advanced bookmark system
- [ ] Reading statistics
- [ ] Social features (sharing, reviews)
- [ ] Audiobook support
- [ ] Cloud sync
- [ ] Advanced search filters
- [ ] Reading recommendations