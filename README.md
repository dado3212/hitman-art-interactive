All credit right now goes to https://gitlab.com/hitmaps/hitmaps.

TODO: Fix up this README and gut the irrelevant logic.

# HITMAPS
HITMAPS is an interactive site for viewing maps for the Hitman series.

# Getting Started
1. Fork the repo to your own GitLab account
2. In the Config folder, make the following changes:
    1. Copy Settings.php.example to Settings.php (don't rename it as this example file should stay in source control). Then fill out the following properties:
        1. `databaseHost`: Your MySQL server's host location
        2. `databaseUser`: Your MySQL server database user
        3. `databasePassword`: The password to your MySQL database
        4. `databaseName`: The name to your MySQL database
        5. `accessKey`: A random string
        6. `loggingEnvironment`: Keep this 'development'
        7. `loggingAccessToken`: If you want to use Rollbar integration for logging,
        put your Rollbar token here.
        8. `superSecretPublicCode`: The registration code to register an account
        9. `recaptchaSiteKey`: Use '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI'
        10. `recaptchaSiteSecret`: Use '6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe'
JWT Config
4. Run `composer install` in the root folder to install all necessary `composer` dependencies
6. Run `php -S localhost:8000` to run the local PHP development server and get started with development 🙂

# Contributing
1. Open a new issue in the issues section explaining what change / feature you want to work on. That way, the project owner is aware of the change you want to make and can help with any questions, etc.
2. Make the necessary changes on your fork, making sensible commits along the way
3. Open a merge request to submit your changes once they are complete

# This version
v13.1.0 (failed), v13.6.0 (failed), 13.14.0
nvm install v13.14.0 (npm 6.14.4)
npm install (with a 2GB swap instance on an ec2 micro)
php -v 7.4.5

vendor/bin/phinx migrate -e development

composer install
composer upgrade ?

## Start the PHP API
* php -S 0.0.0.0:8174

## Start the frontend
* sudo su
* nvm use v13.14.0
* npm run build (?)
* npm run serve

Got redacted database dump from MrMike.
- http://alexbeals.com:8080/games/hitman/sapienza/world-of-tomorrow (what doesn't really work)

TODO: Update this for the repo and a shareable MySQL backend.
Create a database `hitman_art_interactive` with the collation `utf8mb4_unicode_ci`.

`mysql --user=<user> --password=<password> --database=hitman_art_interactive --default-character-set=utf8mb4 < import.sql`

`DROP TABLE elusive_targets; DROP TABLE redacted_data; DROP TABLE roulette_matchups; DROP TABLE roulette_messages; DROP TABLE roulette_objectives; DROP TABLE spin_history; DROP TABLE phinxlog;`

# Legal
HITMAN™, HITMAN™ 2, the HITMAN™ logo, images, and text are the property of IO Interactive.