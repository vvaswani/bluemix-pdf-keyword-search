# PDF Keyword Search

This repository accompanies the IBM developerWorks article. It's built with PHP, Slim Framework 3.x and Bootstrap. It uses various services, including Bluemix Object Storage, Watson Document Conversion and AlchemyAPI.

The steps below assume that an Object Storage service and Document Conversion service have been instantiated via the the Bluemix console. 

To deploy this application to your local development environment:

 * Clone the repository to your local system.
 * Run `composer update` to install all dependencies.
 * Create `config.php` with credentials for your MongoDB database, Bluemix Object Storage service, Watson Document Conversion service and AlchemyAPI authentication token. Use `config.php.sample` as an example.
 * Define a virtual host pointing to the `public` directory, as described in the article.
 
To deploy this application to your Bluemix space:

 * Clone the repository to your local system.
 * Run `composer update` to install all dependencies.
 * Create `config.php` with credentials for your MongoDB database and AlchemyAPI authentication token. Use `config.php.sample` as an example.
 * Update `manifest.yml` with your custom hostname.
 * Push the application to Bluemix and bind Object Storage and Document Conversion services to it, as described in the article.
 
A demo instance is available on Bluemix at [http://pdf-keyword-search.mybluemix.net](http://pdf-keyword-search.mybluemix.net).

###### NOTE: The demo instance is available on a public URL and you should be careful to avoid posting sensitive or confidential documents to it. Use the "Reset System" function in the footer to delete all data and restore the system to its default state.