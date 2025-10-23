# Dijitalsalon - Wedding Event Management System

A comprehensive wedding event management system with Flutter mobile app and PHP web backend.

## 🎯 Features

### 📱 Mobile App (Flutter)
- **Event Management**: Create, join, and manage wedding events
- **Media Sharing**: Upload photos and videos with real-time updates
- **Stories**: Share temporary stories (24-hour content)
- **Comments & Likes**: Interactive social features
- **Permission System**: Role-based access control (Admin, Moderator, Authorized User, Participant)
- **Smart Navigation**: Automatic screen routing based on event access period
- **Real-time Updates**: Live media, comments, and like counts
- **User Management**: Ban/unban users, grant/revoke permissions

### 🌐 Web Panel (PHP)
- **Admin Dashboard**: Complete event and user management
- **Event Creation**: Create events with custom packages
- **Participant Management**: Manage event participants and permissions
- **Media Management**: View and moderate uploaded content
- **Payment System**: Integrated payment tracking for delivery services
- **Reports**: Comprehensive analytics and reporting

### 🗄️ Database Features
- **MySQL Database**: Robust relational database structure
- **User Roles**: Admin, Moderator, Authorized User, Participant
- **Permission System**: JSON-based granular permissions
- **Package System**: Different access levels and durations
- **Payment Tracking**: Complete financial management

## 🚀 Quick Start

### Prerequisites
- PHP 8.0+
- MySQL 8.0+
- Flutter 3.0+
- XAMPP (recommended for local development)

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/oakkalp/Dijitalsalon.git
   cd Dijitalsalon
   ```

2. **Database Setup**
   ```bash
   # Import the database schema
   mysql -u root -p < database_schema.sql
   ```

3. **Web Backend Setup**
   ```bash
   # Configure database connection
   cp config/database.php.example config/database.php
   # Edit config/database.php with your database credentials
   ```

4. **Flutter App Setup**
   ```bash
   cd digimobil_new
   flutter pub get
   flutter run
   ```

## 📁 Project Structure

```
Dijitalsalon/
├── digimobil_new/           # Flutter mobile app
│   ├── lib/
│   │   ├── models/         # Data models
│   │   ├── screens/        # UI screens
│   │   ├── widgets/        # Reusable widgets
│   │   ├── services/       # API services
│   │   └── providers/      # State management
│   └── pubspec.yaml
├── digimobiapi/            # PHP API endpoints
│   ├── login.php
│   ├── events.php
│   ├── participants.php
│   └── ...
├── admin/                  # Web admin panel
│   ├── index.php
│   ├── my_events.php
│   └── ...
├── config/                 # Configuration files
├── uploads/               # File uploads
├── database_schema.sql   # Database structure
└── README.md
```

## 🔐 User Roles & Permissions

### Admin
- Full system access
- User management
- Event management
- Payment management

### Moderator
- Full access to events they create
- Participant management
- Media moderation
- Permission granting

### Authorized User
- Custom permissions per event
- Can be granted specific capabilities
- Limited by event access period

### Participant
- Basic event participation
- Media sharing (during active period)
- Comments and likes
- Limited by event access period

## 📱 Mobile App Features

### Event Management
- **Smart Navigation**: Events automatically route to appropriate screens based on access period
- **Real-time Updates**: Live media, comments, and statistics
- **Permission-based UI**: Interface adapts based on user permissions

### Media Sharing
- **Photo/Video Upload**: Support for multiple file types
- **Stories**: Temporary content with 24-hour expiration
- **Comments & Replies**: Nested comment system
- **Likes**: Interactive like system

### User Management
- **Ban System**: Ban/unban users with immediate effect
- **Permission Management**: Grant/revoke specific permissions
- **Role Updates**: Real-time role changes

## 🌐 Web Panel Features

### Event Management
- **Event Creation**: Create events with custom packages
- **Participant Management**: Add/remove participants
- **Permission System**: Grant specific permissions to users
- **Media Moderation**: View and manage uploaded content

### Payment System
- **Delivery Tracking**: Complete order management
- **Payment Records**: Track all financial transactions
- **Balance Management**: User balance tracking

## 🔧 API Endpoints

### Authentication
- `POST /digimobiapi/login.php` - User login
- `POST /digimobiapi/register.php` - User registration

### Events
- `GET /digimobiapi/events.php` - Get user events
- `POST /digimobiapi/create_event.php` - Create new event
- `POST /digimobiapi/join_event.php` - Join event via QR code

### Media
- `POST /digimobiapi/add_media.php` - Upload media
- `POST /digimobiapi/add_story.php` - Upload story
- `GET /digimobiapi/media.php` - Get event media

### Comments
- `GET /digimobiapi/comments.php` - Get media comments
- `POST /digimobiapi/comments.php` - Add comment
- `DELETE /digimobiapi/delete_comment.php` - Delete comment

### Participants
- `GET /digimobiapi/participants.php` - Get event participants
- `POST /digimobiapi/grant_permissions.php` - Grant permissions
- `POST /digimobiapi/update_participant.php` - Update participant

## 🎨 UI/UX Features

### Smart Screen Routing
- **Active Events**: Full Event Detail Screen with sharing capabilities
- **Expired Events**: Event Profile Screen (view-only)
- **Future Events**: Event Detail Screen (limited sharing)

### Real-time Updates
- **Live Media**: New uploads appear instantly
- **Comment Counts**: Real-time comment statistics
- **Like Counts**: Live like updates
- **User Status**: Immediate ban/unban effects

### Responsive Design
- **Mobile-first**: Optimized for mobile devices
- **Adaptive UI**: Interface adapts to user permissions
- **Dark Mode Support**: Automatic theme adaptation

## 🔒 Security Features

- **Password Hashing**: Bcrypt encryption
- **Session Management**: Secure session handling
- **Permission Validation**: Server-side permission checks
- **File Upload Security**: Type and size validation
- **SQL Injection Protection**: Prepared statements

## 📊 Database Schema

The system uses a comprehensive MySQL database with the following main tables:

- `kullanicilar` - User accounts and profiles
- `dugunler` - Event information
- `dugun_katilimcilar` - Event participants and permissions
- `medyalar` - Uploaded media files
- `hikayeler` - Story content
- `yorumlar` - Comments and replies
- `begeniler` - Like system
- `paketler` - Event packages
- `odemeler` - Payment tracking
- `bakiye` - User balances

## 🚀 Deployment

### Production Setup
1. Configure production database
2. Set up file upload directories
3. Configure web server (Apache/Nginx)
4. Set up SSL certificates
5. Configure Flutter app for production API

### Environment Variables
- Database credentials
- API endpoints
- File upload paths
- Security keys

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 📞 Support

For support and questions:
- Create an issue on GitHub
- Contact: [Your Contact Information]

## 🔄 Version History

- **v1.0.0** - Initial release with basic event management
- **v1.1.0** - Added permission system and user management
- **v1.2.0** - Implemented real-time updates and smart navigation
- **v1.3.0** - Added payment system and comprehensive reporting

---

**Dijitalsalon** - Making wedding events more memorable and organized! 💒✨