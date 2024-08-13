## Development

Clone the repository

```sh
git clone git@github.com:diego-mascarenhas/humano.git
cd humano
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