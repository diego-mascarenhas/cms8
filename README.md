## About CMS8

CMS8 is a web application with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [The PHP Framework for Web Artisans](https://laravel.com).
- [Build Scalable, High-Performance Web Applications](https://pixinvent.com/vuexy-bootstrap-html-admin-template).
- [Spatie Roles and Permissions](https://spatie.be/docs/laravel-permission/v5/introduction) for robust role and permission management.
- [GrapesJS, Web Builder Framework](https://grapesjs.com).
- [Twilio Messaging](https://www.twilio.com/docs/sms) for integrating SMS and messaging services.
- [WHM/cPanel Management](https://documentation.cpanel.net) for managing hosting services and server configurations.
- [Laravel Sail & Docker](https://demos.pixinvent.com/vuexy-html-admin-template/documentation/laravel-sail-docker.html).

CMS8 is accessible, powerful, and provides tools required for large, robust applications.

## Development

Clone the repository

```sh
git clone git@github.com:diego-mascarenhas/cms8.git
cd cms8
composer install
```

Configure environment variables

```sh
cp .env.example .env
vi .env
```

Generating Application Key and Running Migrations

```sh
php artisan key:generate
php artisan migrate
```

Install all the necessary dependencies (`yarn` is highly recommended)

```sh
rm -rf node_modules
rm -rf package-lock.json
rm -rf yarn.lock

npm cache clean --force

npm install --legacy-peer-deps

yarn

npm run dev
```

Start the dev server

```sh
php artisan serve
```

Once the development server is started you should be able to reach the demo page (eg. `http://localhost:8080`)

## Contributing

Thank you for considering contributing to the CMS8 admin!

## Security Vulnerabilities

If you discover a security vulnerability within CMS8, please send an e-mail to Diego Mascarenhas Goytía via [diego.mascarenhas@icloud.com](mailto:diego.mascarenhas@icloud.com). All security vulnerabilities will be promptly addressed.

## License

The CMS8 admin is open-sourced software licensed under the [GNU Affero General Public License v3.0](https://www.gnu.org/licenses/agpl-3.0.html).

### Additional Terms

By deploying this software, you agree to notify the original author at [diego.mascarenhas@icloud.com] or by visiting [http://linkedin.com/in/diego-mascarenhas/]. Any modifications or enhancements must be shared with the original author.