pipeline {
    agent any

    triggers {
        githubPush()
    }

    environment {
        // Target server
        TARGET_SERVER = '52.66.229.213'
        TARGET_USER   = 'ubuntu'
        DEPLOY_PATH   = '/var/www/html/travel-app'

        // Credentials from Jenkins
        AWS_ACCESS_KEY_ID     = credentials('aws-access-key-id')
        AWS_SECRET_ACCESS_KEY = credentials('aws-secret-access-key')
        DB_PASSWORD           = credentials('db-password')
    }

    stages {
        stage('Checkout Code') {
            steps {
                echo 'Pulling code from GitHub...'
                git branch: 'main', url: 'https://github.com/AishwaryaPawar149/php-app-by-cicd.git'
            }
        }

        stage('Validate Files') {
            steps {
                echo 'Validating project files...'
                sh '''
                    if [ ! -f "index.html" ]; then
                        echo "ERROR: index.html not found"
                        exit 1
                    fi
                    if [ ! -f "submit.php" ]; then
                        echo "ERROR: submit.php not found"
                        exit 1
                    fi
                    echo "All required files present"
                '''
            }
        }

        stage('Prepare Deployment Package') {
            steps {
                echo 'Creating deployment package...'
                sh '''
                    mkdir -p deploy_package
                    cp index.html deploy_package/
                    cp submit.php deploy_package/
                    cp .gitignore deploy_package/
                    echo "Deployment package ready"
                '''
            }
        }

        stage('Deploy to Target Server') {
            steps {
                echo 'Deploying to target server...'
                sshagent(['target-server-ssh-key']) {
                    sh """
                        ssh -o StrictHostKeyChecking=no ${TARGET_USER}@${TARGET_SERVER} '
                            sudo mkdir -p ${DEPLOY_PATH}
                            sudo chown ${TARGET_USER}:${TARGET_USER} ${DEPLOY_PATH}
                        '
                        scp -o StrictHostKeyChecking=no -r deploy_package/* ${TARGET_USER}@${TARGET_SERVER}:${DEPLOY_PATH}/
                        ssh -o StrictHostKeyChecking=no ${TARGET_USER}@${TARGET_SERVER} '
                            cd ${DEPLOY_PATH}
                            sudo chown -R www-data:www-data .
                            sudo chmod -R 755 .
                            sudo find . -type f -exec chmod 644 {} \\;
                            sudo find . -type d -exec chmod 755 {} \\;
                        '
                    """
                }
            }
        }

        stage('Create Config File') {
            steps {
                echo 'Creating config.php with credentials...'
                sshagent(['target-server-ssh-key']) {
                    sh """
                        ssh -o StrictHostKeyChecking=no ${TARGET_USER}@${TARGET_SERVER} '
                            cd ${DEPLOY_PATH}
                            cat > config.php << EOF
<?php
define("AWS_ACCESS_KEY_ID", "${AWS_ACCESS_KEY_ID}");
define("AWS_SECRET_ACCESS_KEY", "${AWS_SECRET_ACCESS_KEY}");
define("S3_BUCKET_NAME", "travel-memory-bucket-by-aish");
define("S3_REGION", "ap-south-1");

define("DB_HOST", "database-2.cdwkuiksmrsm.ap-south-1.rds.amazonaws.com");
define("DB_PORT", "3306");
define("DB_NAME", "travel_memory_db");
define("DB_USER", "root");
define("DB_PASSWORD", "${DB_PASSWORD}");

define("DB_TABLE", "travel_memories");
?>
EOF
                            sudo chmod 600 config.php
                            sudo chown www-data:www-data config.php
                        '
                    """
                }
            }
        }

        stage('Install Dependencies') {
            steps {
                echo 'Installing Composer dependencies...'
                sshagent(['target-server-ssh-key']) {
                    sh """
                        ssh -o StrictHostKeyChecking=no ${TARGET_USER}@${TARGET_SERVER} '
                            cd ${DEPLOY_PATH}
                            if ! command -v composer &> /dev/null; then
                                curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer
                            fi
                            if [ ! -d "vendor" ]; then
                                composer require aws/aws-sdk-php
                            fi
                        '
                    """
                }
            }
        }

        stage('Restart Web Server') {
            steps {
                echo 'Restarting web server...'
                sshagent(['target-server-ssh-key']) {
                    sh """
                        ssh -o StrictHostKeyChecking=no ${TARGET_USER}@${TARGET_SERVER} '
                            sudo systemctl restart apache2 || sudo systemctl restart nginx
                        '
                    """
                }
            }
        }

        stage('Health Check') {
            steps {
                echo 'Performing health check...'
                script {
                    def response = sh(
                        script: "curl -s -o /dev/null -w '%{http_code}' http://${TARGET_SERVER}/",
                        returnStdout: true
                    ).trim()
                    
                    if (response == '200') {
                        echo "✅ Health check passed! Application is running."
                    } else {
                        error "⚠️ Health check failed with status ${response}"
                    }
                }
            }
        }
    }

    post {
        success {
            echo 'Deployment completed successfully!'
        }
        failure {
            echo 'Deployment failed!'
        }
        always {
            echo 'Cleaning workspace...'
            cleanWs()
        }
    }
}
