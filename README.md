# public-ip-to-cloudflare

[![](https://images.microbadger.com/badges/image/limenet/public-ip-to-cloudflare.svg)](https://microbadger.com/images/limenet/public-ip-to-cloudflare)

Sets some Cloudflare record to the public IP of the server the script is running on. Very useful as a cheap-and-dirty dynamic DNS solution.

## Usage

### PHP

Create a `.env` file by running `cp .env.example .env` and customizing it to suit your needs and then execute the script using `php run.php`.

### Docker

`docker run -e "DOMAIN=example.com" -e "SUBDOMAIN=some.example.com" -e "CLOUDFLARE_EMAIL=info@example.com" -e "CLOUDFLARE_KEY=c0ffee" -e "RECORD_TYPE=A" limenet/public-ip-to-cloudflare`
