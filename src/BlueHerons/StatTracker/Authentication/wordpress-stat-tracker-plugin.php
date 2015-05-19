<?php
/*
Plugin Name:   Stat Tracker Integration
Description:   Supplemental plugin for Stat Tracker
Version:       0.1
Author:        John (CaptCynicism)
Author URI:    http://blueheronsresistance.com
License:       Apache License 2.0
*/

namespace BlueHerons\Wordpress\Plugin\StatTracker;

define("ST_CAPABILITY",        "access_stat_tracker");
define("ST_USER_AUTH_FILTER",  "stat_tracker_user_auth");
define("ST_AGENT_NAME_FILTER", "stat_tracker_agent_name");

class StatTrackerPlugin {

    /**
     * Filter for determining what agent name should be used in Stat Tracker. 
     *
     * The filter is given the user_login for a user. Any lookups must use this as a starting point
     *
     * @param $name user_login from a WP_User object
     *
     * @return name that Stat Tracker will use for the agent.
     */
    public function agentName($name) {
        return $name;
    }

    /**
     * Filter for determining which Wordpress user's are authorized to use Stat Tracker.
     *
     * This filter is given a WP_User object. You are free to inspect it however you want. If you determine that
     * the WP_User should be given access, simply ensure that this function returns the same WP_User.
     * If theyshould not be given access, there are two options:
     * - Return "null" for a simple "Access Denied" message
     * - Return a string to show them a custom message
     *
     * @param WP_User $user the WP_User to determine authorization for
     *
     * @return WP_User if access is authorized, null if not.
     */
    public function authorizedUser($user) {
        if ($user->has_cap(ST_CAPABILITY)) {
            return $user;
        }
        else {
            $admins = get_users(array('role' => 'administrator'));
            $list = array();
            foreach ($admins as $admin) {
                if ($admin->has_cap(ST_CAPABILITY)) {
                    $list[] = $admin->user_login;
                }
            }
            return __("You must be approved to use Stat Tracker. Please contact an administrator to get approved: ") .
                  join(", ", $list);
                   
        }
    }

    /**
     * Filter to add a new column to the user list
     */
    function addUserListColumns($columns) {
	if (is_admin() && current_user_can(ST_CAPABILITY)) {
		$columns[ST_CAPABILITY] = __("Stat Tracker", "stat-tracker");
	}
	return $columns;
    }

    /**
     * Filter for showing a checkmark in the user list next to users who have access to Stat Tracker
    */
    function userListColumns($value = '', $column_name, $user_id) {
        if (get_user_by('id', $user_id)->has_cap("bhr_access_stat_tracker")) {
            get_user_by('id', $user_id)->add_cap(ST_CAPABILITY);
        }
        if (ST_CAPABILITY == $column_name) {
            $value = get_user_by('id', $user_id)->has_cap(ST_CAPABILITY) ? "&#x2713;" : "";
        }

        return $value;
    }

    /**
     * Hook for modifying the User profile page to add a checkbox for admins with access to Stat Tracker to approve new users for Stat Tracker
     */
    function userProfilePage($user) {
?>
    <h3><?php _e("Stat Tracker", "stat-tracker"); ?></h3>
    <table class="form-table">
        <tr>
	    <th>
		<label for="bhr_stat_tracker"><?php _e("Stat Tracker Access", "stat-tracker"); ?></label>
	    </th>
	    <td><?php
        // Checkbox is enabled only for admins viewing another person's profile
        $is_disabled = is_admin() && get_current_user_id() != $user->ID;
                ?><input type="checkbox" name="<?php echo ST_CAPABILITY;?>" id="<?php echo ST_CAPABILITY;?>" <?php echo $user->has_cap(ST_CAPABILITY) ? "checked " : ""; echo $is_disabled ? "" : "disabled "; ?>/><br/>
                <span class="description"><?php _e("Enables access to Stat Tracker.", "stat-tracker"); ?></span>
            </td>
        </tr>
    </table>
<?php
    }

    /**
     * Hook for saving the ST_CAPABILITY to the user's permissions
     */
    function userProfileUpdate($user_id) {
        if (isset($_REQUEST[ST_CAPABILITY])) {
            get_userdata($user_id)->add_cap(ST_CAPABILITY);
        }
        else {
            get_userdata($user_id)->remove_cap(ST_CAPABILITY);
        }
    }
}

$plugin = new StatTrackerPlugin();

add_filter(ST_USER_AUTH_FILTER, array($plugin, "authorizedUser"));
add_filter(ST_AGENT_NAME_FILTER, array($plugin, "agentName"));

add_filter("manage_users_columns", array($plugin, "addUserListColumns"));
add_filter("manage_users_custom_column", array($plugin, "userListColumns"), 10, 3);

add_action("show_user_profile", array($plugin, "userProfilePage"));
add_action("edit_user_profile", array($plugin, "userProfilePage"));

add_action("personal_options_update", array($plugin, "userProfileUpdate"));
add_action("edit_user_profile_update", array($plugin, "userProfileUpdate"));
?>
