<?php
/*
   Copyright 2011 Michael L. Baker

   Licensed under the Apache License, Version 2.0 (the "License");
   you may not use this file except in compliance with the License.
   You may obtain a copy of the License at

       http://www.apache.org/licenses/LICENSE-2.0

   Unless required by applicable law or agreed to in writing, software
   distributed under the License is distributed on an "AS IS" BASIS,
   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
   See the License for the specific language governing permissions and
   limitations under the License.

   Notes: Based on the Time Tracking plugin by Elmar:
   2005 by Elmar Schumacher - GAMBIT Consulting GmbH
   http://www.mantisbt.org/forums/viewtopic.php?f=4&t=589
*/
require_once( 'core/timetracking_api.php' ); 
class TimeTrackingPlugin extends MantisPlugin {

	function register() {
		$this->name = 'Time Tracking';
		$this->description = 'Time tracking plugin that supports entering date worked, time and notes. Also includes limited permissions per user.';
		$this->page = 'config_page';

		$this->version = '2.0.8';
		$this->requires = array(
			'MantisCore' => '2.25.0'
		);

		$this->author = 'Elmar Schumacher, Michael Baker, Erwann Penet';
		$this->contact = '';
		$this->url = 'https://github.com/Krzyska-Filip/Mantis-timetracking';
	}

	function hooks() {
		return array(
			'EVENT_VIEW_BUG_EXTRA' => 'view_bug_time',
			'EVENT_MENU_ISSUE'     => 'timerecord_menu',
			'EVENT_MENU_MAIN'      => 'showreport_menu',
		);
	}

	function config() {
		return array(
            'reporter_view'         => REPORTER,
			'admin_own_threshold'   => DEVELOPER,
			'view_others_threshold' => MANAGER,
			'admin_threshold'       => ADMINISTRATOR,
			'categories'       => ''
		);
	}

	function init() {
		$t_path = config_get_global('plugin_path' ). plugin_get_current() . DIRECTORY_SEPARATOR . 'core' . DIRECTORY_SEPARATOR;
		set_include_path(get_include_path() . PATH_SEPARATOR . $t_path);
	}


	/**
	 * Show TimeTracking information when viewing bugs.
	 * @param string Event name
	 * @param int Bug ID
	 */
	function view_bug_time( $p_event, $p_bug_id ) {
		$t_table = plugin_table('data');
		$t_user_id = auth_get_current_user_id();

		if( access_has_bug_level( plugin_config_get( 'reporter_view' ), $p_bug_id ) ) {
			$t_plugin_TimeTracking_stats = plugin_TimeTracking_stats_get_project_array( ' ', ' ', ' ', ' ', $p_bug_id);
		} else {
			// User has no access
			return;
		}

		# Get Sum for this bug
		db_param_push();
		$t_query_pull_hours = plugin_sum_hours_query();
		$t_result_pull_hours = db_query( $t_query_pull_hours, array($p_bug_id, $p_bug_id) );
		$t_row_pull_hours = db_fetch_array( $t_result_pull_hours );

		$t_collapse_block = is_collapsed( 'timerecord' );
		$t_block_css = $t_collapse_block ? 'collapsed' : '';
		$t_block_icon = $t_collapse_block ? 'fa-chevron-down' : 'fa-chevron-up';
?>

<div class="col-md-12 col-xs-12 noprint">
<a id="timerecord"></a>
<div class="space-10"></div>

	<div id="timerecord_add" class="widget-box widget-color-blue2 <?php echo $t_block_css ?>">
		<div class="widget-header widget-header-small">
			<h4 class="widget-title lighter">
				<i class="ace-icon fa fa-clock-o"></i>
				<?php echo plugin_lang_get( 'title' ) ?>
			</h4>
			<div class="widget-toolbar">
				<a data-action="collapse" href="#">
					<i class="1 ace-icon fa <?php echo $t_block_icon ?> bigger-125"></i>
				</a>
			</div>
		</div>

   <form name="time_tracking" method="post" action="<?php echo plugin_page('add_record') ?>" >
      <?php echo form_security_field( 'plugin_TimeTracking_add_record' ) ?>
      <input type="hidden" name="bug_id" value="<?php echo $p_bug_id; ?>"/>
      <input type="hidden" name="time_category" value="<?php echo plugin_get_bug_category($p_bug_id) ?>"/>

		<div class="widget-body">
		<div class="widget-main no-padding">

   <div class="table-responsive">

<?php
		if ( access_has_bug_level( plugin_config_get( 'admin_own_threshold' ), $p_bug_id ) ) {
			$t_current_date = explode("-", date("Y-m-d"));
?>
   <table class="width100" style="border-spacing: 5px 0; border-collapse: separate;">
   <tr class="row-category">
      <td><div align="center"><b><?php echo plugin_lang_get( 'expenditure_date' ); ?></b></div></td>
      <td><div align="center"><b><?php echo plugin_lang_get( 'hours' ); ?></b></div></td>
      <td><div align="center"><b><?php echo plugin_lang_get( 'information' ); ?></b></div></td>
      <td>&nbsp;</td>
   </tr>

   <tr>
     <td nowrap>
        <div align="center">
           <select tabindex="5" name="day"><?php print_day_option_list( $t_current_date[2] ) ?></select>
           <select tabindex="6" name="month"><?php print_month_option_list( $t_current_date[1] ) ?></select>
           <select tabindex="7" name="year"><?php print_year_option_list( $t_current_date[0] ) ?></select>
        </div>
     </td>
     <td><div align="right"><input type="text" name="time_value" value="00:00" size="5" pattern="^(?!00:00$)([0-9]{1,2}|0[0-9]{1}):[0-5][0-9]$" required title="Please enter a valid time (HH:MM) between 00:01 and 99:59"/></div></td>
     <td><div align="center"><input type="text" name="time_info"/></div></td>
     <td>
         <input type="submit"
                class="btn btn-primary btn-sm btn-white btn-round"
                value="<?php echo plugin_lang_get( 'submit' ) ?>" />
     </td>
   </tr>

</table>
<?php
		} # END Access Control
?>
   </div>

   <div class="table-responsive">
   <table class="table table-bordered table-condensed table-hover table-striped">
   <thead>
   <tr>
      <th class="small-caption" style="width: 150px;"><?php echo plugin_lang_get( 'user' ); ?></th>
      <th class="small-caption" style="width: 120px;"><?php echo plugin_lang_get( 'expenditure_date' ); ?></th>
      <th class="small-caption" style="width: 60px;"><?php echo plugin_lang_get( 'hours' ); ?></th>
      <th class="small-caption" style="width: 60px;"><?php echo lang_get( ( 'bugnote' ) ); ?></th>
      <th class="small-caption"><?php echo plugin_lang_get( 'information' ); ?></th>
      <th class="small-caption" style="width: 170px;"><?php echo plugin_lang_get( 'entry_date' ); ?></th>
      <th class="small-caption" style="width: 60px;">&nbsp;</th>
   </tr>
   </thead>


<?php
		foreach ( $t_plugin_TimeTracking_stats as $t_row ) {
?>


<tbody>
   <tr>
	  <?php
		$t_note_info = '&nbsp;';
		if(!$t_row["is_new_tt"])
			$t_note_info = '<a rel="bookmark" href="' . string_get_bugnote_view_url( $p_bug_id, $t_row["id"]) . '" class="lighter" title="' . lang_get( 'bugnote_link_title' ) . '">
			' . bugnote_format_id($t_row["id"]) . '
			</a>'
	  ?>
      <td class="small-caption" style="width: 150px;"><?php echo $t_row["username"]; ?></td>
      <td class="small-caption" style="width: 120px;"><?php echo date( config_get("short_date_format"), strtotime($t_row["expenditure_date"])); ?> </td>
      <td class="small-caption" style="width: 60px;" ><?php echo plugin_TimeTracking_hours_to_hhmm($t_row["hours"]) ?> </td>
	  <td class="small-caption" style="width: 60px;"><?php echo $t_note_info; ?> </td>
      <td class="small-caption"><?php echo string_display_links($t_row["info"]); ?></td>
      <td class="small-caption" style="width: 170px;"><?php echo date( config_get("complete_date_format"), strtotime($t_row["timestamp"])); ?> </td>

<?php
			if( $t_row["is_new_tt"] && (($t_user_id == $t_row["user_id"] && access_has_bug_level( plugin_config_get( 'admin_own_threshold' ), $p_bug_id) ) || access_has_bug_level( plugin_config_get( 'admin_threshold' ), $p_bug_id)) ) {
				echo '<td class="small-caption" style="width: 60px;"><a href="'.plugin_page('delete_record').'?>&bug_id='.$p_bug_id.'&delete_id='.$t_row["id"].form_security_param( 'plugin_TimeTracking_delete_record' ).'">'.plugin_lang_get( 'delete' ).'</a></td>';
			}
			elseif( $t_row["is_new_tt"] && !(($t_user_id == $t_row["user_id"] && access_has_bug_level( plugin_config_get( 'admin_own_threshold' ), $p_bug_id) ) || access_has_bug_level( plugin_config_get( 'admin_threshold' ), $p_bug_id))){
				echo '<td class="small-caption"><span title="'.plugin_lang_get('delete_info').'">Info</span></td>';
			}
			else {
				echo '<td class="small-caption"><span title="'.plugin_lang_get('record_info').'">Info</span></td>';
			}
?>
   </tr>


<?php
		} # End for loop
?>


   </tbody>
   <tfoot>
   <tr class="row-category">
      <td class="small-caption"><?php echo plugin_lang_get( 'sum' ) ?></td>
      <td class="small-caption">&nbsp;</td>
      <td class="small-caption"><b><?php echo plugin_TimeTracking_hours_to_hhmm( $t_row_pull_hours['hours'] ); ?></b></td>
      <td class="small-caption">&nbsp;</td>
      <td class="small-caption">&nbsp;</td>
      <td class="small-caption">&nbsp;</td>
   </tr>
   </tfoot>
</table>
   </div>

</div>
</div>
</div>
</form>

</div>

<?php
	} # function end

	function schema() {
		return array(
			array( 'CreateTableSQL', array( plugin_table( 'data' ), "
				id                 I       NOTNULL UNSIGNED AUTOINCREMENT PRIMARY,
				bug_id             I       DEFAULT NULL UNSIGNED,
				user               I       DEFAULT NULL UNSIGNED,
				expenditure_date   T       DEFAULT NULL,
				hours              F(15,3) DEFAULT NULL,
				timestamp          T       DEFAULT NULL,
				category           C(255)  DEFAULT NULL,
				info               C(255)  DEFAULT NULL
				" )
			),
		);
	}

	function timerecord_menu() {
		$bugid =  gpc_get_int( 'id' );
		if( access_has_bug_level( plugin_config_get( 'admin_own_threshold' ), $bugid )
		 || access_has_bug_level( plugin_config_get( 'view_others_threshold' ), $bugid ) ) {
			$import_page = 'view.php?';
			$import_page .= 'id=';
			$import_page .= $bugid ;
			$import_page .= '#timerecord';

			return array( plugin_lang_get( 'timerecord_menu' ) => $import_page);
		}
		else {
			return array ();
		}
	}

	function showreport_menu() {
		return array(
			array(
				'title' => plugin_lang_get( 'title' ),
				'access_level' => plugin_config_get( 'admin_own_threshold' ),
				'url' => plugin_page( 'show_report' ),
				'icon' => 'fa-random'
			)
		);
	}
} # class end
?>
