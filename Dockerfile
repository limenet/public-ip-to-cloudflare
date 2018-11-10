FROM php:alpine

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /app

COPY composer.json .
COPY composer.lock .

RUN composer install

RUN touch .env

COPY . .

CMD ["php", "run.php"]
