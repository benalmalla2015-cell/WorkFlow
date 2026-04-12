# WorkFlow Management System

A comprehensive full-stack business management system for handling orders, quotations, invoices, and multi-role user management with strict privacy controls.

## Features

### 🏢 Multi-Role System
- **Sales Employees**: Create orders, manage customers, generate quotations and invoices
- **Factory Employees**: Handle cost entry, supplier management, production planning
- **System Administrators**: User management, profit margin control, system monitoring

### 📋 Order Management
- Complete order lifecycle from creation to payment confirmation
- Multi-file attachment support (Word, Excel, PDF, Images)
- Real-time status tracking and notifications
- Customer and factory data privacy separation

### 📄 Document Generation
- **Excel Quotations**: Professional quotations with company branding
- **PDF Invoices**: Detailed invoices with payment information
- **QR Code Integration**: Document verification with embedded QR codes
- **Employee Attribution**: Automatic salesperson name inclusion

### 🔒 Security & Privacy
- **Data Encryption**: Sensitive data (prices, customer info) encrypted in database
- **Role-Based Access**: Eloquent Global Scopes for data separation
- **Audit Logging**: Complete activity tracking for compliance
- **Secure File Storage**: AWS S3 integration with organized paths

### 📊 Analytics & Reporting
- Real-time dashboard with key metrics
- Profit analysis and margin tracking
- Employee performance reports
- Price fluctuation monitoring

## Technology Stack

### Backend
- **Laravel 10**: PHP framework with robust features
- **MySQL**: Database with encrypted sensitive fields
- **AWS S3**: File storage and backup system
- **Maatwebsite Excel**: Excel document generation
- **DomPDF**: PDF generation with custom templates
- **Simple QrCode**: QR code generation for verification

### Frontend
- **React 18**: Modern JavaScript framework
- **Ant Design**: Professional UI component library
- **React Query**: Data fetching and state management
- **Recharts**: Interactive charts and analytics
- **Axios**: HTTP client for API communication

### DevOps
- **GitHub**: Version control and repository
- **Hostinger**: Production hosting environment
- **Automated Backups**: Daily database backups to S3
- **SSH Deployment**: Secure deployment pipeline

## Installation

### Prerequisites
- PHP 8.1+
- MySQL 8.0+
- Node.js 16+
- Composer
- AWS S3 Account

### Local Development Setup

1. **Clone Repository**
   ```bash
   git clone https://github.com/benalmalla2015-cell/WorkFlow.git
   cd WorkFlow
   ```

2. **Backend Setup**
   ```bash
   composer install
   cp .env.example .env
   php artisan key:generate
   php artisan migrate
   php artisan db:seed
   ```

3. **Frontend Setup**
   ```bash
   cd frontend
   npm install
   npm start
   ```

4. **Environment Configuration**
   ```bash
   # Update .env with your settings
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_DATABASE=workflow
   DB_USERNAME=root
   DB_PASSWORD=your_password
   
   # AWS S3 Configuration
   AWS_ACCESS_KEY_ID=your_key
   AWS_SECRET_ACCESS_KEY=your_secret
   AWS_DEFAULT_REGION=us-east-1
   AWS_BUCKET=your_bucket
   ```

## Default Login Credentials

- **Admin**: admin@workflow.com / admin123
- **Sales**: sales@workflow.com / sales123
- **Factory**: factory@workflow.com / factory123

## Deployment

### Automated Deployment
Run the deployment script:
```bash
chmod +x deploy.sh
./deploy.sh
```

### Manual Deployment to Hostinger
1. Connect via SSH: `ssh -p 65002 u859266589@45.13.255.111`
2. Navigate to deployment directory
3. Pull latest changes and run deployment commands

## System Architecture

### Database Schema
- **Users**: Role-based authentication system
- **Customers**: Customer information management
- **Orders**: Central order management with encrypted pricing
- **Attachments**: File management with type separation
- **Suppliers**: Factory supplier information
- **Settings**: System configuration management
- **Audit Logs**: Complete activity tracking

### Privacy Implementation
- **Sales View**: Customer data visible, factory data hidden
- **Factory View**: Technical specs visible, customer data hidden
- **Admin View**: Complete data access for management

### Document Flow
1. Sales creates order with customer details
2. Factory adds cost and supplier information
3. Admin reviews and sets profit margin
4. System generates quotation (Excel)
5. Customer approval triggers invoice (PDF)
6. Payment confirmation completes the cycle

## Security Features

### Data Protection
- Database field encryption for sensitive information
- Role-based data access through Eloquent Global Scopes
- Secure file upload with type validation
- API authentication with Laravel Sanctum

### Audit & Compliance
- Complete audit trail of all user actions
- IP address and user agent tracking
- Document verification with QR codes
- Automated backup system with retention policies

## API Endpoints

### Authentication
- `POST /api/login` - User authentication
- `POST /api/logout` - User logout
- `GET /api/me` - Current user information

### Orders
- `GET /api/orders` - List orders (filtered by role)
- `POST /api/orders` - Create new order
- `GET /api/orders/{id}` - Get order details
- `PUT /api/orders/{id}` - Update order
- `POST /api/orders/{id}/approve` - Admin approval

### Documents
- `POST /api/orders/{id}/quotation` - Generate Excel quotation
- `POST /api/orders/{id}/invoice` - Generate PDF invoice
- `GET /api/orders/verify/{orderNumber}` - Verify document

### Admin
- `GET /api/admin/dashboard/stats` - Dashboard statistics
- `GET /api/admin/users` - User management
- `PUT /api/admin/settings` - System settings
- `GET /api/admin/audit-logs` - Activity logs

## Support & Maintenance

### Monitoring
- System health checks
- Database backup verification
- File storage monitoring
- Performance metrics tracking

### Updates
- Regular security patches
- Feature enhancements
- Bug fixes and optimizations
- Documentation updates

## License

This is a proprietary system. All rights reserved.

## Contact

For support and maintenance:
- Email: support@dayancosys.com
- Website: https://dayancosys.com

---

**WorkFlow System** - Streamlining Business Operations with Security and Efficiency
