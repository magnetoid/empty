import React, { createContext, useContext, useState, useEffect, ReactNode } from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { WooCommerceAPI } from '../services/WooCommerceAPI';

export interface Book {
  id: number;
  name: string;
  description: string;
  price: string;
  regular_price: string;
  sale_price?: string;
  images: Array<{
    id: number;
    src: string;
    name: string;
  }>;
  download_url?: string;
  file_type: 'pdf' | 'epub';
  file_size?: string;
  pages?: number;
  author?: string;
  publisher?: string;
  isbn?: string;
  published_date?: string;
  reading_progress?: number;
  last_read?: string;
  is_downloaded?: boolean;
  local_path?: string;
}

interface BookContextType {
  books: Book[];
  purchasedBooks: Book[];
  isLoading: boolean;
  fetchBooks: () => Promise<void>;
  fetchPurchasedBooks: () => Promise<void>;
  downloadBook: (book: Book) => Promise<boolean>;
  updateReadingProgress: (bookId: number, progress: number) => Promise<void>;
  getBookById: (id: number) => Book | undefined;
  searchBooks: (query: string) => Book[];
}

const BookContext = createContext<BookContextType | undefined>(undefined);

export const useBooks = () => {
  const context = useContext(BookContext);
  if (context === undefined) {
    throw new Error('useBooks must be used within a BookProvider');
  }
  return context;
};

interface BookProviderProps {
  children: ReactNode;
}

export const BookProvider: React.FC<BookProviderProps> = ({ children }) => {
  const [books, setBooks] = useState<Book[]>([]);
  const [purchasedBooks, setPurchasedBooks] = useState<Book[]>([]);
  const [isLoading, setIsLoading] = useState(false);

  useEffect(() => {
    fetchBooks();
    loadPurchasedBooks();
  }, []);

  const loadPurchasedBooks = async () => {
    try {
      const storedBooks = await AsyncStorage.getItem('purchased_books');
      if (storedBooks) {
        setPurchasedBooks(JSON.parse(storedBooks));
      }
    } catch (error) {
      console.error('Error loading purchased books:', error);
    }
  };

  const fetchBooks = async () => {
    try {
      setIsLoading(true);
      const response = await WooCommerceAPI.getBooks();
      if (response.success) {
        setBooks(response.books);
      }
    } catch (error) {
      console.error('Error fetching books:', error);
    } finally {
      setIsLoading(false);
    }
  };

  const fetchPurchasedBooks = async () => {
    try {
      setIsLoading(true);
      const response = await WooCommerceAPI.getPurchasedBooks();
      if (response.success) {
        setPurchasedBooks(response.books);
        await AsyncStorage.setItem('purchased_books', JSON.stringify(response.books));
      }
    } catch (error) {
      console.error('Error fetching purchased books:', error);
    } finally {
      setIsLoading(false);
    }
  };

  const downloadBook = async (book: Book): Promise<boolean> => {
    try {
      const response = await WooCommerceAPI.downloadBook(book.id);
      if (response.success) {
        const updatedBook = { ...book, is_downloaded: true, local_path: response.file_path };
        const updatedPurchasedBooks = purchasedBooks.map(b => 
          b.id === book.id ? updatedBook : b
        );
        setPurchasedBooks(updatedPurchasedBooks);
        await AsyncStorage.setItem('purchased_books', JSON.stringify(updatedPurchasedBooks));
        return true;
      }
      return false;
    } catch (error) {
      console.error('Error downloading book:', error);
      return false;
    }
  };

  const updateReadingProgress = async (bookId: number, progress: number) => {
    try {
      const updatedBooks = purchasedBooks.map(book => 
        book.id === bookId 
          ? { ...book, reading_progress: progress, last_read: new Date().toISOString() }
          : book
      );
      setPurchasedBooks(updatedBooks);
      await AsyncStorage.setItem('purchased_books', JSON.stringify(updatedBooks));
    } catch (error) {
      console.error('Error updating reading progress:', error);
    }
  };

  const getBookById = (id: number): Book | undefined => {
    return [...books, ...purchasedBooks].find(book => book.id === id);
  };

  const searchBooks = (query: string): Book[] => {
    const searchTerm = query.toLowerCase();
    return books.filter(book => 
      book.name.toLowerCase().includes(searchTerm) ||
      book.description.toLowerCase().includes(searchTerm) ||
      (book.author && book.author.toLowerCase().includes(searchTerm))
    );
  };

  const value: BookContextType = {
    books,
    purchasedBooks,
    isLoading,
    fetchBooks,
    fetchPurchasedBooks,
    downloadBook,
    updateReadingProgress,
    getBookById,
    searchBooks,
  };

  return <BookContext.Provider value={value}>{children}</BookContext.Provider>;
};