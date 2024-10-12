FROM alpine:edge

LABEL Maintainer="BohwaZ <https://bohwaz.net/>" \
      Description="oPodSync"


# Install required packages
RUN apk --no-cache add php83 php83-ctype php83-opcache php83-session php83-sqlite3

# Create a non-root user
RUN addgroup -S www && adduser -S www -G www

# Setup document root and permissions
RUN mkdir -p /var/www/server/data && \
    chown -R www:www /var/www

# Set the working directory
WORKDIR /var/www

# Add application files
COPY --chown=appuser:appgroup server /var/www/server

# Expose application port
EXPOSE 8080

# Define the volume for data
VOLUME ["/var/www/server/data"]

# Set PHP environment variables for security
ENV PHP_CLI_SERVER_WORKERS="2"
ENV PHP_INI_SCAN_DIR="/etc/php83/conf.d"
ENV PHP_DISPLAY_ERRORS="0"
ENV PHP_EXPOSE_PHP="0"

# Use non-root user to run the application
USER www

# Start the PHP built-in server
CMD ["php", "-S", "0.0.0.0:8080", "-t", "server", "server/index.php"]
