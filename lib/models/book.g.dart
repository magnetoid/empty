// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'book.dart';

// **************************************************************************
// TypeAdapterGenerator
// **************************************************************************

class BookAdapter extends TypeAdapter<Book> {
  @override
  final int typeId = 0;

  @override
  Book read(BinaryReader reader) {
    final numOfFields = reader.readByte();
    final fields = <int, dynamic>{
      for (int i = 0; i < numOfFields; i++) reader.readByte(): reader.read(),
    };
    return Book(
      id: fields[0] as int,
      name: fields[1] as String,
      description: fields[2] as String,
      price: fields[3] as String,
      regularPrice: fields[4] as String,
      salePrice: fields[5] as String?,
      images: (fields[6] as List).cast<BookImage>(),
      downloadUrl: fields[7] as String?,
      fileType: fields[8] as BookFileType,
      fileSize: fields[9] as String?,
      pages: fields[10] as int?,
      author: fields[11] as String?,
      publisher: fields[12] as String?,
      isbn: fields[13] as String?,
      publishedDate: fields[14] as String?,
      readingProgress: fields[15] as double,
      lastRead: fields[16] as DateTime?,
      isDownloaded: fields[17] as bool,
      localPath: fields[18] as String?,
      isPurchased: fields[19] as bool,
    );
  }

  @override
  void write(BinaryWriter writer, Book obj) {
    writer
      ..writeByte(20)
      ..writeByte(0)
      ..write(obj.id)
      ..writeByte(1)
      ..write(obj.name)
      ..writeByte(2)
      ..write(obj.description)
      ..writeByte(3)
      ..write(obj.price)
      ..writeByte(4)
      ..write(obj.regularPrice)
      ..writeByte(5)
      ..write(obj.salePrice)
      ..writeByte(6)
      ..write(obj.images)
      ..writeByte(7)
      ..write(obj.downloadUrl)
      ..writeByte(8)
      ..write(obj.fileType)
      ..writeByte(9)
      ..write(obj.fileSize)
      ..writeByte(10)
      ..write(obj.pages)
      ..writeByte(11)
      ..write(obj.author)
      ..writeByte(12)
      ..write(obj.publisher)
      ..writeByte(13)
      ..write(obj.isbn)
      ..writeByte(14)
      ..write(obj.publishedDate)
      ..writeByte(15)
      ..write(obj.readingProgress)
      ..writeByte(16)
      ..write(obj.lastRead)
      ..writeByte(17)
      ..write(obj.isDownloaded)
      ..writeByte(18)
      ..write(obj.localPath)
      ..writeByte(19)
      ..write(obj.isPurchased);
  }

  @override
  int get hashCode => typeId.hashCode;

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is BookAdapter &&
          runtimeType == other.runtimeType &&
          typeId == other.typeId;
}

class BookImageAdapter extends TypeAdapter<BookImage> {
  @override
  final int typeId = 1;

  @override
  BookImage read(BinaryReader reader) {
    final numOfFields = reader.readByte();
    final fields = <int, dynamic>{
      for (int i = 0; i < numOfFields; i++) reader.readByte(): reader.read(),
    };
    return BookImage(
      id: fields[0] as int,
      src: fields[1] as String,
      name: fields[2] as String,
    );
  }

  @override
  void write(BinaryWriter writer, BookImage obj) {
    writer
      ..writeByte(3)
      ..writeByte(0)
      ..write(obj.id)
      ..writeByte(1)
      ..write(obj.src)
      ..writeByte(2)
      ..write(obj.name);
  }

  @override
  int get hashCode => typeId.hashCode;

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is BookImageAdapter &&
          runtimeType == other.runtimeType &&
          typeId == other.typeId;
}

class BookFileTypeAdapter extends TypeAdapter<BookFileType> {
  @override
  final int typeId = 2;

  @override
  BookFileType read(BinaryReader reader) {
    switch (reader.readByte()) {
      case 0:
        return BookFileType.pdf;
      case 1:
        return BookFileType.epub;
      default:
        return BookFileType.pdf;
    }
  }

  @override
  void write(BinaryWriter writer, BookFileType obj) {
    switch (obj) {
      case BookFileType.pdf:
        writer.writeByte(0);
        break;
      case BookFileType.epub:
        writer.writeByte(1);
        break;
    }
  }

  @override
  int get hashCode => typeId.hashCode;

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is BookFileTypeAdapter &&
          runtimeType == other.runtimeType &&
          typeId == other.typeId;
}