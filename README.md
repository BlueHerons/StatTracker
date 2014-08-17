# Stat Tracker

Stat Tracker is a web app for [Ingress](http://ingress.com) agents to track and predict thier progression to levels and badges, and compare themselves to other agents in thier faction.

Stat Tracker relies on agents to submit thier own data. It does not pull any data from the Ingress app or Niantic servers, and thus is not believed to violate the Ingress TOS.

## Setup

To run your own instance of Stat Tracker, you will need:

 * A LAMP (**L** inux, **A** pache, **M** ySQL, **P** HP) server
   * [composer](http://getcomposer.org) will need to be installed
 * A [Google Developer](http://console.developers.google.com) account
 * An SMTP server (You're exisitng email service should provide one)
 
1. In the web directory of your server, run `git clone https://github.com/BlueHerons/StatTracker.git` to pull down the latest code
2. Edit `config.php` and provide the required credentials
  * `GOOGLE_*` options are provided from the Google Developer account. You will need to create a new project within your account in order to obtain a client ID and client secret.
3. Run `composer update` to download all the dependencies.
4. Execute each SQL script in `database/tables`, and then in `database/procedures`
  * You will need to create a MySQL user named `admin` to satisfy the definer in the procedure defintions.
