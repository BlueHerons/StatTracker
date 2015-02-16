# Stat Tracker

Stat Tracker is a web app for [Ingress](http://ingress.com) agents to track and predict their progression to levels and badges, and compare themselves to other agents in their local group, or across factions.

Stat Tracker relies on agents to submit their own data. It does not pull any data from the Ingress app or Niantic servers, and thus is not believed to violate the Ingress TOS.

## Setup Overview

### Prerequisities
 * A LAMP (**L** inux, **A** pache, **M** ySQL, **P** HP) server
 * [composer](http://getcomposer.org)
 * [ocrad](http://www.gnu.org/software/ocrad/)
 * A [Google Developer](http://console.developers.google.com) account (for OAuth)
 * An SMTP server (Your existing email service should provide one)

For directions and more information on how to install these prerequisities see [Setting up Stat Tracker](../../wiki/Setting-Up-Stat-Tracker)

### Set up Stat Tracker
1. Clone this repository
2. Run `composer describe-version`
3. Run `composer update`
3. Copy `config.php.sample` to `config.php` and set the values appropriately
4. Execute each SQL script in [database/tables](database/tables), [database/procedures](database/procedures) and [database/functions](database/functions)

For more details, please refer to [Setting up Stat Tracker](../../wiki/Setting-Up-Stat-Tracker)

## Administration

When a user tries to access Stat Tracker for the first time, they will be emailed an activation code -- 6 hexadecimal digits. The email instructs the user to send their activation code over Faction COMM in the scanner app to an agent (likely you). Once you receive an in-game message with an activation code:

1. Open up the `Agents` table
2. Find the row with the given activation code
3. Update the `agent` column to be the agents name that sent you the code.

**WARNING:** Once you do this, the user will have access to your instance of Stat Tracker. You *should* go to other lengths to verify the agent's identity. If you need to revoke access for any reason, set the value of the `agent` column to an empty string, or delete the entire row.
