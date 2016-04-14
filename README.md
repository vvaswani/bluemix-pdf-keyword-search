# PDF Keyword Search

This repository accompanies the IBM developerWorks article and is configured for use on IBM Bluemix. It assumes that an Object Storage service and Document Conversion service will be bound to the application through the Bluemix console. 

To deploy this application to your Bluemix space:

 * Clone the repository to your local system.
 * Run `composer update` to install all dependencies.
 * Update `config.php` with credentials for your MongoDB database and AlchemyAPI authentication token. Use `config.php.sample` as an example.
 * Update `manifest.yml` with your custom hostname.
 * Push the application to Bluemix and bind Object Storage and Document Conversion services to it.

A demo instance is available at [http://pdf-keyword-search.mybluemix.net](http://pdf-keyword-search.mybluemix.net).


###### NOTE: The demo application is available on a public URL and you should be careful to avoid posting sensitive or confidential documents to it. Use the "Reset System" function in the footer to delete all data and restore the system to its default state.