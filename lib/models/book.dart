import 'package:hive/hive.dart';

part 'book.g.dart';

@HiveType(typeId: 0)
class Book {
  @HiveField(0)
  final int id;
  
  @HiveField(1)
  final String name;
  
  @HiveField(2)
  final String description;
  
  @HiveField(3)
  final String price;
  
  @HiveField(4)
  final String regularPrice;
  
  @HiveField(5)
  final String? salePrice;
  
  @HiveField(6)
  final List<BookImage> images;
  
  @HiveField(7)
  final String? downloadUrl;
  
  @HiveField(8)
  final BookFileType fileType;
  
  @HiveField(9)
  final String? fileSize;
  
  @HiveField(10)
  final int? pages;
  
  @HiveField(11)
  final String? author;
  
  @HiveField(12)
  final String? publisher;
  
  @HiveField(13)
  final String? isbn;
  
  @HiveField(14)
  final String? publishedDate;
  
  @HiveField(15)
  final double readingProgress;
  
  @HiveField(16)
  final DateTime? lastRead;
  
  @HiveField(17)
  final bool isDownloaded;
  
  @HiveField(18)
  final String? localPath;
  
  @HiveField(19)
  final bool isPurchased;

  Book({
    required this.id,
    required this.name,
    required this.description,
    required this.price,
    required this.regularPrice,
    this.salePrice,
    required this.images,
    this.downloadUrl,
    required this.fileType,
    this.fileSize,
    this.pages,
    this.author,
    this.publisher,
    this.isbn,
    this.publishedDate,
    this.readingProgress = 0.0,
    this.lastRead,
    this.isDownloaded = false,
    this.localPath,
    this.isPurchased = false,
  });

  factory Book.fromJson(Map<String, dynamic> json) {
    return Book(
      id: json['id'] ?? 0,
      name: json['name'] ?? '',
      description: json['description'] ?? '',
      price: json['price'] ?? '0',
      regularPrice: json['regular_price'] ?? '0',
      salePrice: json['sale_price'],
      images: (json['images'] as List<dynamic>?)
          ?.map((img) => BookImage.fromJson(img))
          .toList() ?? [],
      downloadUrl: json['download_url'],
      fileType: _parseFileType(json['file_type']),
      fileSize: json['file_size'],
      pages: json['pages'],
      author: json['author'],
      publisher: json['publisher'],
      isbn: json['isbn'],
      publishedDate: json['published_date'],
      readingProgress: (json['reading_progress'] ?? 0.0).toDouble(),
      lastRead: json['last_read'] != null 
          ? DateTime.parse(json['last_read']) 
          : null,
      isDownloaded: json['is_downloaded'] ?? false,
      localPath: json['local_path'],
      isPurchased: json['is_purchased'] ?? false,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'description': description,
      'price': price,
      'regular_price': regularPrice,
      'sale_price': salePrice,
      'images': images.map((img) => img.toJson()).toList(),
      'download_url': downloadUrl,
      'file_type': fileType.toString().split('.').last,
      'file_size': fileSize,
      'pages': pages,
      'author': author,
      'publisher': publisher,
      'isbn': isbn,
      'published_date': publishedDate,
      'reading_progress': readingProgress,
      'last_read': lastRead?.toIso8601String(),
      'is_downloaded': isDownloaded,
      'local_path': localPath,
      'is_purchased': isPurchased,
    };
  }

  Book copyWith({
    int? id,
    String? name,
    String? description,
    String? price,
    String? regularPrice,
    String? salePrice,
    List<BookImage>? images,
    String? downloadUrl,
    BookFileType? fileType,
    String? fileSize,
    int? pages,
    String? author,
    String? publisher,
    String? isbn,
    String? publishedDate,
    double? readingProgress,
    DateTime? lastRead,
    bool? isDownloaded,
    String? localPath,
    bool? isPurchased,
  }) {
    return Book(
      id: id ?? this.id,
      name: name ?? this.name,
      description: description ?? this.description,
      price: price ?? this.price,
      regularPrice: regularPrice ?? this.regularPrice,
      salePrice: salePrice ?? this.salePrice,
      images: images ?? this.images,
      downloadUrl: downloadUrl ?? this.downloadUrl,
      fileType: fileType ?? this.fileType,
      fileSize: fileSize ?? this.fileSize,
      pages: pages ?? this.pages,
      author: author ?? this.author,
      publisher: publisher ?? this.publisher,
      isbn: isbn ?? this.isbn,
      publishedDate: publishedDate ?? this.publishedDate,
      readingProgress: readingProgress ?? this.readingProgress,
      lastRead: lastRead ?? this.lastRead,
      isDownloaded: isDownloaded ?? this.isDownloaded,
      localPath: localPath ?? this.localPath,
      isPurchased: isPurchased ?? this.isPurchased,
    );
  }

  static BookFileType _parseFileType(String? fileType) {
    switch (fileType?.toLowerCase()) {
      case 'pdf':
        return BookFileType.pdf;
      case 'epub':
        return BookFileType.epub;
      default:
        return BookFileType.pdf;
    }
  }

  String get displayPrice {
    if (salePrice != null && salePrice!.isNotEmpty) {
      return salePrice!;
    }
    return price;
  }

  bool get hasDiscount {
    return salePrice != null && salePrice!.isNotEmpty && salePrice != price;
  }

  String get coverImageUrl {
    if (images.isNotEmpty) {
      return images.first.src;
    }
    return '';
  }
}

@HiveType(typeId: 1)
class BookImage {
  @HiveField(0)
  final int id;
  
  @HiveField(1)
  final String src;
  
  @HiveField(2)
  final String name;

  BookImage({
    required this.id,
    required this.src,
    required this.name,
  });

  factory BookImage.fromJson(Map<String, dynamic> json) {
    return BookImage(
      id: json['id'] ?? 0,
      src: json['src'] ?? '',
      name: json['name'] ?? '',
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'src': src,
      'name': name,
    };
  }
}

@HiveType(typeId: 2)
enum BookFileType {
  @HiveField(0)
  pdf,
  @HiveField(1)
  epub,
}