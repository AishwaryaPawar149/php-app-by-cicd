pipeline {
    agent any

    triggers {
        githubPush()
    }

    environment {
        TARGET_SERVER = '52.66.229.213'       // Target server IP
        TARGET_USER   = 'ubuntu'              // SSH user
        DEPLOY_PATH   = '/var/www/html/travel-app' // Deployment folder

        AWS_ACCESS_KEY_ID     = credentials('aws-access-key-id')
        AWS_SECRET_ACCESS_KEY = credentials('aws-secret-access-key')
        DB_PASSWORD           = credentials('db-password')
    }

    stages {
        stage('Checkout') {
            steps {
                echo 'Cloning repository...'
                git branch: 'main', url: 'https://github.com/AishwaryaPawar149/php-app-by-cicd.git'
            }
        }

        stage('Validate Files') {
            steps {
                echo 'Checking required files...'
                sh '''
                    for f in index.html submit.php composer.json; do
                        if [ ! -f "$f" ]; then
                            echo "ERROR: $f not found"
                            exit 1
                        fi
                    done
                    echo "All required files present"
                '''
            }
        }

        stage('Prepare Deployment Package') {
            steps {
                echo 'Creating deployment package...'
                sh '''
                    rm -rf deploy_package
                    mkdir deploy_package
                    cp index.html submit.php composer.json deploy_package/
                    echo "Package ready"
                '''
            }
        }

        stage('Deploy to Server') {
            steps {
                sshagent(['target-server-ssh-key']) {
                    sh """
                    # Create deployment folder
                    ssh -o StrictHostKeyChecking=no ${TARGET_USER}@${TARGET_SERVER} '
                        sudo mkdir -p ${DEPLOY_PATH}
                        sudo chown ${TARGET_USER}:${TARGET_USER} ${DEPLOY_PATH}
                    '

                    # Copy files
                    scp -o StrictHostKeyChecking=no -r deploy_package/* ${TARGET_USER}@${TARGET_SERVER}:${DEPLOY_PATH}/
                    
                    # Install dependencies
                    ssh -o StrictHostKeyChecking=no ${TARGET_USER}@${TARGET_SERVER} '
                        cd ${DEPLOY_PATH}
                        if ! command -v composer &> /dev/null; then
                            curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer
                        fi
                        composer install --no-dev
                    '
                    
                    # Create config.php
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

                    # Set permissions
                    ssh -o StrictHostKeyChecking=no ${TARGET_USER}@${TARGET_SERVER} '
                        sudo chown -R www-data:www-data ${DEPLOY_PATH}
                        sudo find ${DEPLOY_PATH} -type d -exec chmod 755 {} \;
                        sudo find ${DEPLOY_PATH} -type f -exec chmod 644 {} \;
                    '

                    # Restart web server
                    ssh -o StrictHostKeyChecking=no ${TARGET_USER}@${TARGET_SERVER} '
                        sudo systemctl restart apache2 || sudo systemctl restart nginx
                    '
                    """
                }
            }
        }

        stage('Health Check') {
            steps {
                echo 'Checking server health...'
                script {
                    def status = sh(script: "curl -s -o /dev/null -w '%{http_code}' http://${TARGET_SERVER}/index.html", returnStdout: true).trim()
                    if (status == '200') {
                        echo "✅ Server is up!"
                    } else {
                        error "❌ Health check failed. Status code: ${status}"
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
            cleanWs()
        }
    }
}
