pipeline {
    agent any

    triggers {
        githubPush()
    }

    environment {
        TARGET_SERVER = '52.66.229.213'         // Replace with your target server IP
        TARGET_USER   = 'ubuntu'                // SSH user
        DEPLOY_PATH   = '/var/www/html/travel-app'  // Deployment directory
    }

    stages {
        stage('Checkout Code') {
            steps {
                echo 'Pulling code from GitHub...'
                git branch: 'main',
                    url: 'https://github.com/AishwaryaPawar149/php-app-by-cicd.git',
                    credentialsId: 'pull-key'
            }
        }

        stage('Validate Files') {
            steps {
                echo 'Validating project files...'
                sh '''
                    if [ ! -f "index.html" ] || [ ! -f "submit.php" ]; then
                        echo "ERROR: Required files missing"
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
                    cp .env deploy_package/
                    echo "Deployment package ready"
                '''
            }
        }

        stage('Deploy to Target Server') {
            steps {
                echo 'Deploying to target server...'
                sshagent(['target-server-ssh-key']) {
                    sh """
                        scp -o StrictHostKeyChecking=no -r deploy_package/* ${TARGET_USER}@${TARGET_SERVER}:${DEPLOY_PATH}/
                        ssh -o StrictHostKeyChecking=no ${TARGET_USER}@${TARGET_SERVER} '
                            sudo chown -R www-data:www-data ${DEPLOY_PATH}
                            sudo find ${DEPLOY_PATH} -type d -exec chmod 755 {} \\;
                            sudo find ${DEPLOY_PATH} -type f -exec chmod 644 {} \\;
                            echo "Files deployed and permissions set"
                        '
                    """
                }
            }
        }

        stage('Load Environment') {
            steps {
                echo 'Loading environment variables from .env on server...'
                sshagent(['target-server-ssh-key']) {
                    sh """
                        ssh -o StrictHostKeyChecking=no ${TARGET_USER}@${TARGET_SERVER} '
                            cd ${DEPLOY_PATH}
                            export \$(grep -v "^#" .env | xargs)
                            echo "Environment variables loaded"
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
                            composer install --no-dev
                            echo "Dependencies installed"
                        '
                    """
                }
            }
        }

        stage('Restart Web Server') {
            steps {
                echo 'Restarting Apache/Nginx...'
                sshagent(['target-server-ssh-key']) {
                    sh """
                        ssh -o StrictHostKeyChecking=no ${TARGET_USER}@${TARGET_SERVER} '
                            sudo systemctl restart apache2 || sudo systemctl restart nginx
                            echo "Web server restarted"
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
                        error "❌ Health check failed! Status code: ${response}"
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
