<?php
// Include configuration
require_once 'config.php';

// Include AWS SDK (installed via Composer)
require 'vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Invalid request method');
}

// Validate and sanitize input
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$location = isset($_POST['location']) ? trim($_POST['location']) : '';
$memory = isset($_POST['memory']) ? trim($_POST['memory']) : '';

// Validate required fields
if (empty($name) || empty($email) || empty($location) || empty($memory)) {
    die('All fields are required');
}

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die('Invalid email format');
}

// Check if file is uploaded
if (!isset($_FILES['travel_image']) || $_FILES['travel_image']['error'] !== UPLOAD_ERR_OK) {
    die('Error uploading file');
}

$file = $_FILES['travel_image'];
$fileName = $file['name'];
$fileTmpPath = $file['tmp_name'];
$fileSize = $file['size'];
$fileType = $file['type'];

// Validate file type
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
if (!in_array($fileType, $allowedTypes)) {
    die('Only image files (JPG, PNG, GIF) are allowed');
}

// Validate file size (max 5MB)
if ($fileSize > 5 * 1024 * 1024) {
    die('File size must be less than 5MB');
}

// Generate unique filename
$fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
$uniqueFileName = 'travel_' . time() . '_' . uniqid() . '.' . $fileExtension;
$s3Key = 'images/' . $uniqueFileName;

try {
    // Initialize S3 Client
    $s3Client = new S3Client([
        'version' => 'latest',
        'region' => S3_REGION,
        'credentials' => [
            'key' => AWS_ACCESS_KEY_ID,
            'secret' => AWS_SECRET_ACCESS_KEY,
        ],
    ]);

    // Upload file to S3
    $result = $s3Client->putObject([
        'Bucket' => S3_BUCKET_NAME,
        'Key' => $s3Key,
        'SourceFile' => $fileTmpPath,
        'ContentType' => $fileType,
    ]);

    // Get the S3 URL
    $s3Url = $result['ObjectURL'];

    // Connect to RDS MySQL Database
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // Create table if not exists
    $createTableSQL = "CREATE TABLE IF NOT EXISTS " . DB_TABLE . " (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        location VARCHAR(255) NOT NULL,
        memory TEXT NOT NULL,
        image_url VARCHAR(500) NOT NULL,
        image_filename VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($createTableSQL);

    // Insert data into database
    $sql = "INSERT INTO " . DB_TABLE . " (name, email, location, memory, image_url, image_filename) 
            VALUES (:name, :email, :location, :memory, :image_url, :image_filename)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':name' => $name,
        ':email' => $email,
        ':location' => $location,
        ':memory' => $memory,
        ':image_url' => $s3Url,
        ':image_filename' => $uniqueFileName,
    ]);

    // Success response
    echo "<!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Success</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                justify-content: center;
                align-items: center;
                margin: 0;
            }
            .success-box {
                background: white;
                padding: 40px;
                border-radius: 15px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                text-align: center;
                max-width: 500px;
            }
            h1 { color: #28a745; margin-bottom: 20px; }
            p { color: #333; margin-bottom: 10px; line-height: 1.6; }
            .image-preview {
                margin: 20px 0;
                max-width: 100%;
                border-radius: 8px;
                box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            }
            a {
                display: inline-block;
                margin-top: 20px;
                padding: 12px 30px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                text-decoration: none;
                border-radius: 8px;
                font-weight: 600;
            }
            a:hover { opacity: 0.9; }
        </style>
    </head>
    <body>
        <div class='success-box'>
            <h1>✅ Success!</h1>
            <p><strong>Thank you, " . htmlspecialchars($name) . "!</strong></p>
            <p>Your travel memory from <strong>" . htmlspecialchars($location) . "</strong> has been uploaded successfully.</p>
            <img src='" . htmlspecialchars($s3Url) . "' alt='Travel Memory' class='image-preview' style='max-height: 300px;'>
            <p style='font-size: 12px; color: #666;'>Image stored in S3 and data saved to database</p>
            <a href='index.html'>Upload Another Memory</a>
        </div>
    </body>
    </html>";

} catch (AwsException $e) {
    // S3 Error
    die("S3 Error: " . $e->getMessage());
} catch (PDOException $e) {
    // Database Error
    die("Database Error: " . $e->getMessage());
} catch (Exception $e) {
    // General Error
    die("Error: " . $e->getMessage());
}
?>