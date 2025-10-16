import axios, { AxiosInstance } from 'axios';
import AsyncStorage from '@react-native-async-storage/async-storage';
import RNFS from 'react-native-fs';
import { Book } from '../context/BookContext';

interface WooCommerceConfig {
  url: string;
  consumerKey: string;
  consumerSecret: string;
}

interface AuthResponse {
  success: boolean;
  token?: string;
  user?: any;
  message?: string;
}

interface BooksResponse {
  success: boolean;
  books?: Book[];
  message?: string;
}

interface DownloadResponse {
  success: boolean;
  file_path?: string;
  message?: string;
}

class WooCommerceAPI {
  private static instance: WooCommerceAPI;
  private api: AxiosInstance;
  private config: WooCommerceConfig | null = null;

  private constructor() {
    this.api = axios.create({
      timeout: 30000,
      headers: {
        'Content-Type': 'application/json',
      },
    });

    this.loadConfig();
  }

  public static getInstance(): WooCommerceAPI {
    if (!WooCommerceAPI.instance) {
      WooCommerceAPI.instance = new WooCommerceAPI();
    }
    return WooCommerceAPI.instance;
  }

  private async loadConfig() {
    try {
      const config = await AsyncStorage.getItem('woocommerce_config');
      if (config) {
        this.config = JSON.parse(config);
        this.setupAPI();
      }
    } catch (error) {
      console.error('Error loading WooCommerce config:', error);
    }
  }

  public async setConfig(config: WooCommerceConfig) {
    this.config = config;
    await AsyncStorage.setItem('woocommerce_config', JSON.stringify(config));
    this.setupAPI();
  }

  private setupAPI() {
    if (!this.config) return;

    this.api = axios.create({
      baseURL: `${this.config.url}/wp-json/wc/v3`,
      timeout: 30000,
      auth: {
        username: this.config.consumerKey,
        password: this.config.consumerSecret,
      },
      headers: {
        'Content-Type': 'application/json',
      },
    });
  }

  public async authenticateUser(email: string, password: string): Promise<AuthResponse> {
    try {
      if (!this.config) {
        return { success: false, message: 'WooCommerce not configured' };
      }

      // For WooCommerce, we'll use the WordPress REST API for authentication
      const response = await axios.post(`${this.config.url}/wp-json/jwt-auth/v1/token`, {
        username: email,
        password: password,
      });

      if (response.data.token) {
        // Get user details
        const userResponse = await axios.get(`${this.config.url}/wp-json/wp/v2/users/me`, {
          headers: {
            Authorization: `Bearer ${response.data.token}`,
          },
        });

        return {
          success: true,
          token: response.data.token,
          user: {
            id: userResponse.data.id,
            email: userResponse.data.email,
            username: userResponse.data.username,
            first_name: userResponse.data.first_name,
            last_name: userResponse.data.last_name,
            avatar_url: userResponse.data.avatar_urls?.['96'],
          },
        };
      }

      return { success: false, message: 'Authentication failed' };
    } catch (error: any) {
      console.error('Authentication error:', error);
      return { 
        success: false, 
        message: error.response?.data?.message || 'Authentication failed' 
      };
    }
  }

  public async registerUser(
    email: string, 
    password: string, 
    firstName: string, 
    lastName: string
  ): Promise<AuthResponse> {
    try {
      if (!this.config) {
        return { success: false, message: 'WooCommerce not configured' };
      }

      // Register user via WordPress REST API
      const response = await axios.post(`${this.config.url}/wp-json/wp/v2/users`, {
        username: email,
        email: email,
        password: password,
        first_name: firstName,
        last_name: lastName,
      }, {
        headers: {
          Authorization: `Basic ${Buffer.from(`${this.config.consumerKey}:${this.config.consumerSecret}`).toString('base64')}`,
        },
      });

      if (response.data.id) {
        // Auto-login after registration
        return await this.authenticateUser(email, password);
      }

      return { success: false, message: 'Registration failed' };
    } catch (error: any) {
      console.error('Registration error:', error);
      return { 
        success: false, 
        message: error.response?.data?.message || 'Registration failed' 
      };
    }
  }

  public async getBooks(): Promise<BooksResponse> {
    try {
      if (!this.config) {
        return { success: false, message: 'WooCommerce not configured' };
      }

      const response = await this.api.get('/products', {
        params: {
          category: 'ebooks', // Assuming you have an 'ebooks' category
          per_page: 50,
          status: 'publish',
        },
      });

      const books: Book[] = response.data.map((product: any) => ({
        id: product.id,
        name: product.name,
        description: product.description,
        price: product.price,
        regular_price: product.regular_price,
        sale_price: product.sale_price,
        images: product.images || [],
        file_type: this.determineFileType(product),
        file_size: product.meta_data?.find((meta: any) => meta.key === '_file_size')?.value,
        pages: product.meta_data?.find((meta: any) => meta.key === '_pages')?.value,
        author: product.meta_data?.find((meta: any) => meta.key === '_author')?.value,
        publisher: product.meta_data?.find((meta: any) => meta.key === '_publisher')?.value,
        isbn: product.meta_data?.find((meta: any) => meta.key === '_isbn')?.value,
        published_date: product.meta_data?.find((meta: any) => meta.key === '_published_date')?.value,
      }));

      return { success: true, books };
    } catch (error: any) {
      console.error('Error fetching books:', error);
      return { 
        success: false, 
        message: error.response?.data?.message || 'Failed to fetch books' 
      };
    }
  }

  public async getPurchasedBooks(): Promise<BooksResponse> {
    try {
      if (!this.config) {
        return { success: false, message: 'WooCommerce not configured' };
      }

      const token = await AsyncStorage.getItem('auth_token');
      if (!token) {
        return { success: false, message: 'Not authenticated' };
      }

      // Get user's orders
      const ordersResponse = await axios.get(`${this.config.url}/wp-json/wc/v3/orders`, {
        headers: {
          Authorization: `Bearer ${token}`,
        },
        params: {
          status: 'completed',
          per_page: 50,
        },
      });

      const purchasedBookIds = new Set<number>();
      const purchasedBooks: Book[] = [];

      // Extract book IDs from completed orders
      for (const order of ordersResponse.data) {
        for (const item of order.line_items) {
          if (item.meta_data?.some((meta: any) => meta.key === '_is_ebook')) {
            purchasedBookIds.add(item.product_id);
          }
        }
      }

      // Fetch book details for purchased books
      for (const bookId of purchasedBookIds) {
        try {
          const bookResponse = await this.api.get(`/products/${bookId}`);
          const product = bookResponse.data;
          
          const book: Book = {
            id: product.id,
            name: product.name,
            description: product.description,
            price: product.price,
            regular_price: product.regular_price,
            sale_price: product.sale_price,
            images: product.images || [],
            download_url: product.meta_data?.find((meta: any) => meta.key === '_download_url')?.value,
            file_type: this.determineFileType(product),
            file_size: product.meta_data?.find((meta: any) => meta.key === '_file_size')?.value,
            pages: product.meta_data?.find((meta: any) => meta.key === '_pages')?.value,
            author: product.meta_data?.find((meta: any) => meta.key === '_author')?.value,
            publisher: product.meta_data?.find((meta: any) => meta.key === '_publisher')?.value,
            isbn: product.meta_data?.find((meta: any) => meta.key === '_isbn')?.value,
            published_date: product.meta_data?.find((meta: any) => meta.key === '_published_date')?.value,
          };

          purchasedBooks.push(book);
        } catch (error) {
          console.error(`Error fetching book ${bookId}:`, error);
        }
      }

      return { success: true, books: purchasedBooks };
    } catch (error: any) {
      console.error('Error fetching purchased books:', error);
      return { 
        success: false, 
        message: error.response?.data?.message || 'Failed to fetch purchased books' 
      };
    }
  }

  public async downloadBook(bookId: number): Promise<DownloadResponse> {
    try {
      if (!this.config) {
        return { success: false, message: 'WooCommerce not configured' };
      }

      const token = await AsyncStorage.getItem('auth_token');
      if (!token) {
        return { success: false, message: 'Not authenticated' };
      }

      // Get download URL for the book
      const response = await axios.get(`${this.config.url}/wp-json/wc/v3/products/${bookId}`, {
        headers: {
          Authorization: `Bearer ${token}`,
        },
      });

      const downloadUrl = response.data.meta_data?.find((meta: any) => meta.key === '_download_url')?.value;
      
      if (!downloadUrl) {
        return { success: false, message: 'Download URL not found' };
      }

      // Create downloads directory
      const downloadsDir = `${RNFS.DocumentDirectoryPath}/downloads`;
      await RNFS.mkdir(downloadsDir, { NSURLIsExcludedFromBackupKey: true });

      // Determine file extension
      const fileExtension = downloadUrl.includes('.pdf') ? '.pdf' : '.epub';
      const fileName = `book_${bookId}${fileExtension}`;
      const filePath = `${downloadsDir}/${fileName}`;

      // Download the file
      const downloadResult = await RNFS.downloadFile({
        fromUrl: downloadUrl,
        toFile: filePath,
        headers: {
          Authorization: `Bearer ${token}`,
        },
      }).promise;

      if (downloadResult.statusCode === 200) {
        return { success: true, file_path: filePath };
      } else {
        return { success: false, message: 'Download failed' };
      }
    } catch (error: any) {
      console.error('Error downloading book:', error);
      return { 
        success: false, 
        message: error.response?.data?.message || 'Download failed' 
      };
    }
  }

  private determineFileType(product: any): 'pdf' | 'epub' {
    const downloadUrl = product.meta_data?.find((meta: any) => meta.key === '_download_url')?.value;
    if (downloadUrl) {
      if (downloadUrl.toLowerCase().includes('.pdf')) {
        return 'pdf';
      } else if (downloadUrl.toLowerCase().includes('.epub')) {
        return 'epub';
      }
    }
    
    // Default to PDF if we can't determine
    return 'pdf';
  }
}

export const WooCommerceAPI = WooCommerceAPI.getInstance();