# Product inventory

A page with a form for products: product name, quantity in stock and price per item.
The form is sent with Ajax and saved to `data/products.json`. The table below the form
updates without reloading the page, shows the total value of each row and the sum of
all totals, and each row can be edited.

GitHub: https://github.com/eynullabeyli/php-skills-test

## Requirements

- PHP 7.4 or newer
- Internet access in the browser (Bootstrap loads from a CDN)

## Run

Extract the zip into your web server's document root and open the folder in a browser,
for example `http://localhost/php-skills-test/`.

Without a web server, run this inside the folder and open http://localhost:8000:

```bash
php -S localhost:8000
```

PHP needs write access to the `data` folder. If it can't write there, the page shows a
warning with the command to fix it.

## Files

- `index.php`: the page
- `api.php`: JSON endpoints the page calls (list, create, update)
- `src/`: saving to the JSON file and validating input
- `assets/`: JavaScript and CSS
- `data/products.json`: the saved products

## Notes

- Written in plain PHP instead of Laravel, so nothing needs to be installed after extracting.
- Times are saved in UTC and shown in the viewer's local time.
- Totals are added up in cents, so there are no rounding errors.
- An edited row keeps its original submission time and its place in the table.
