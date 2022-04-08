## Setup

### Installation

Cartalyst packages utilize [Composer](http://getcomposer.org), for more information on how to install Composer please read the [Composer Documentation](https://getcomposer.org/doc/00-intro.md).

#### Preparation

Open your `composer.json` file and add the following to the `require` array:

	"cartalyst/data-grid-laravel": "^5.0"

Add the following lines after the `require` array on your `composer.json` file:

	"repositories": [
		{ "type": "composer", "url": "https://packages.cartalyst.com" }
	]

> **Note:** Make sure that after the required changes your `composer.json` file is valid by running `composer validate`.

#### Install the dependencies

Run the `composer install` or `composer update` to install or update the new requirement.
