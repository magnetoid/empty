import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:syncfusion_flutter_pdfviewer/pdfviewer.dart';
import '../../models/book.dart';
import '../../utils/app_theme.dart';

class ReaderScreen extends StatefulWidget {
  final Book book;

  const ReaderScreen({
    super.key,
    required this.book,
  });

  @override
  State<ReaderScreen> createState() => _ReaderScreenState();
}

class _ReaderScreenState extends State<ReaderScreen> {
  late PdfViewerController _pdfController;
  bool _isLoading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _pdfController = PdfViewerController();
    _loadBook();
  }

  Future<void> _loadBook() async {
    try {
      if (widget.book.fileType == BookFileType.pdf) {
        setState(() {
          _isLoading = false;
        });
      } else {
        setState(() {
          _error = 'EPUB support coming soon!';
          _isLoading = false;
        });
      }
    } catch (e) {
      setState(() {
        _error = 'Failed to load book: $e';
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(
          widget.book.name,
          style: const TextStyle(
            color: Colors.white,
            fontWeight: FontWeight.w600,
          ),
        ),
        backgroundColor: AppTheme.primaryColor,
        foregroundColor: Colors.white,
        actions: [
          IconButton(
            icon: const Icon(Icons.bookmark_outline),
            onPressed: () {
              // TODO: Implement bookmark functionality
            },
          ),
          IconButton(
            icon: const Icon(Icons.share),
            onPressed: () {
              // TODO: Implement share functionality
            },
          ),
        ],
      ),
      body: _buildBody(),
    );
  }

  Widget _buildBody() {
    if (_isLoading) {
      return const Center(
        child: CircularProgressIndicator(),
      );
    }

    if (_error != null) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(
              Icons.error_outline,
              size: 80,
              color: Colors.grey,
            ),
            const SizedBox(height: 20),
            Text(
              _error!,
              style: const TextStyle(
                fontSize: 16,
                color: Colors.grey,
              ),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 20),
            ElevatedButton(
              onPressed: () {
                Navigator.pop(context);
              },
              child: const Text('Go Back'),
            ),
          ],
        ),
      );
    }

    if (widget.book.fileType == BookFileType.pdf) {
      return _buildPdfViewer();
    } else {
      return _buildEpubViewer();
    }
  }

  Widget _buildPdfViewer() {
    return SfPdfViewer.file(
      widget.book.localPath!,
      controller: _pdfController,
      onDocumentLoadFailed: (PdfDocumentLoadFailedDetails details) {
        setState(() {
          _error = 'Failed to load PDF: ${details.error}';
        });
      },
      onDocumentLoaded: (PdfDocumentLoadedDetails details) {
        // TODO: Load reading progress
      },
      onPageChanged: (PdfPageChangedDetails details) {
        // TODO: Save reading progress
      },
    );
  }

  Widget _buildEpubViewer() {
    return const Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(
            Icons.book,
            size: 80,
            color: Colors.grey,
          ),
          SizedBox(height: 20),
          Text(
            'EPUB support coming soon!',
            style: TextStyle(
              fontSize: 18,
              color: Colors.grey,
            ),
          ),
          SizedBox(height: 10),
          Text(
            'We are working on adding EPUB support',
            style: TextStyle(
              fontSize: 14,
              color: Colors.grey,
            ),
          ),
        ],
      ),
    );
  }
}