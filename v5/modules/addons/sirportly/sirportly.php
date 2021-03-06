<?php
require_once('sirportly_functions.php');

if (!defined("WHMCS"))
	die("This file cannot be accessed directly");

function sirportly_config() {
    $configarray = array(
    "name" => "Sirportly",
    "description" => "",
    "version" => "2.0.2",
    "author" => "aTech Media Ltd",
      "fields" => array(
        "url"         => array ("FriendlyName" => "API URL",             "Type" => "text",      "Size" => "50", "Default" => "api.sirportly.com", "Description" => "Without a trailing /"),
        "token"       => array ("FriendlyName" => "API Token",           "Type" => "text",      "Size" => "50", "Description" => "API token can be generated within the Sirportly interface."),
        "secret"      => array ("FriendlyName" => "API Secret",          "Type" => "text",      "Size" => "50"),
        "ssl"         => array ("FriendlyName" => "Use SSL?",            "Type" => "yesno",     "Size" => "50", "Description" => "Connect to API via SSL?"),
        "frame_key"   => array ("FriendlyName" => "Frame Key",           "Type" => "password",  "Size" => "50", "Default" => "", "Description" => "Frame Key"),
        'staff_url'   => array ("FriendlyName" => "Staff Interface URL", "Type" => "text",      "Size" => "50", "Default" => "", "Description" => "Full URL to your staff interface, etc http://whmcs.sirportly.com/"),
        'kb'          => array ("FriendlyName" => "Knowledge Base ID",   "Type" => "text",      "Size" => "50"),
      )
    );
    return $configarray;
}
function sirportly_activate() {
  $query = "CREATE TABLE IF NOT EXISTS `sirportly` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `field_type` varchar(255) NOT NULL,
      `field_name` varchar(255) NOT NULL,
      UNIQUE KEY `id` (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;";
  $result = mysql_query($query);

  $query = "CREATE TABLE IF NOT EXISTS `sirportly_customers` (
	  `id` int(11) NOT NULL AUTO_INCREMENT,
	  `userid` int(11) NOT NULL,
	  `customerid` int(11) NOT NULL,
	  UNIQUE KEY `id` (`id`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;";
  $result = mysql_query($query);

  mysql_query("INSERT INTO  `tbladdonmodules` (`module` ,`setting`, `value`) VALUES ('sirportly',  'brand', '0');");
  mysql_query("INSERT INTO  `tbladdonmodules` (`module` ,`setting`, `value`) VALUES ('sirportly',  'status', '0');");
  mysql_query("INSERT INTO  `tbladdonmodules` (`module` ,`setting`, `value`) VALUES ('sirportly',  'priority', '0');");

  ## v1.1
  mysql_query("INSERT INTO  `tbladdonmodules` (`module` ,`setting`, `value`) VALUES ('sirportly',  'close_ticket', '0');");
  mysql_query("INSERT INTO  `tbladdonmodules` (`module` ,`setting`, `value`) VALUES ('sirportly',  'closed_status', '0');");

  return array('status'=>'success');
}

function sirportly_upgrade($vars){
  $version = $vars['version'];

  if ($version < 1.1) {
    mysql_query("INSERT INTO  `tbladdonmodules` (`module` ,`setting`, `value`) VALUES ('sirportly',  'close_ticket', '0');");
    mysql_query("INSERT INTO  `tbladdonmodules` (`module` ,`setting`, `value`) VALUES ('sirportly',  'closed_status', '0');");
  }
}

function sirportly_deactivate() {
  $query = "DROP TABLE `sirportly`";
	$result = mysql_query($query);

	$query = "DROP TABLE `sirportly_options`";
	$result = mysql_query($query);

	$query = "DROP TABLE `sirportly_customers`";
	$result = mysql_query($query);

  return array('status'=>'success');
}

function sirportly_output($vars)
{
  echo '
  <p>
    <strong>Options:</strong>
    <a href="addonmodules.php?module=sirportly">Data Source</a> |
    <a href="addonmodules.php?module=sirportly&action=support">Support Tickets</a> |
    <a href="addonmodules.php?module=sirportly&action=merge">Merge Contacts</a>
  </p>';



  switch ($_GET['action']) {
    case 'merge':

      if ($_POST) {
        if (!$_POST['from'] || !$_POST['into']) {
          echo '<div class="errorbox"><strong>An Error Occured!</strong><br />Please select both a "from" and "to" contact.</div>';
        } elseif(!$_POST['from'] or !$_POST['into'] or $_POST['from'] == $_POST['into']) {
          echo '<div class="errorbox"><strong>An Error Occured!</strong><br />You cannot merge contacts into themselves.</div>';
        } else {
          $merge = sirportly_merge_contacts($_POST['from'], $_POST['into']);
          if ($merge['status'] != 200) {
            echo '<div class="errorbox"><strong>An Error Occured!</strong><br />'.$merge['results']['error'].'</div>';
          }else{
            echo '<div class="infobox"><strong>Success!</strong><br />Successfully merged the contacts.</div>';
          }
        }
      }

      $sirportly_contacts = sirportly_contacts();
      $whmcs_contacts     = full_query("SELECT sc.customerid, wc.firstname, wc.lastname, wc.email FROM sirportly_customers sc  LEFT JOIN tblclients wc ON sc.userid = wc.id");


      echo '
        <form method="POST" action="addonmodules.php?module=sirportly&action=merge">
          <table class="form" width="100%" border="0" cellspacing="2" cellpadding="3">
            <tr>
              <td width="100px" class="fieldlabel">Merge</td>
              <td class="fieldarea" width="100px"><select name="from">';
                foreach ($sirportly_contacts as $key => $value) {
                  echo '<option'.($value['id'] == $_POST['from'] ? ' selected=selected':'').' value="'.$value['id'].'">'.$value['name'].' ('.$value['id'].')</option>';
                }
      echo '
             </select> </td>

             <td width="50px" class="fieldlabel">in to</td>
             <td class="fieldarea"width="100px"><select name="into">';
               while ($contact = mysql_fetch_array($whmcs_contacts, MYSQL_ASSOC)) {
                 echo '<option '.($contact['customerid'] == $_POST['into'] ? ' selected=selected':'').' value="'.$contact['customerid'].'">'.$contact['firstname'].' '.$contact['lastname'].' ('.$contact['customerid'].')</option>';
               }
     echo '
            </select> </td>
            </tr></table>

            <p align="center"><input type="submit" value="Merge Contacts" /></p>
            </form>';


    break;
    case 'support':
     if (!$vars['token'] || !$vars['secret']) {
      echo '<div class="errorbox"><strong>An Error Occured!</strong><br />Please enter your API Token and/or Secret.</div>';
      return;
    }

    if ($_POST){
      update_query('tbladdonmodules',array('value' => $_POST['brand']), array('module'=>'sirportly', 'setting' => 'brand'));
      update_query('tbladdonmodules',array('value' => $_POST['status']), array('module'=>'sirportly', 'setting' => 'status'));
      update_query('tbladdonmodules',array('value' => $_POST['priority']), array('module'=>'sirportly', 'setting' => 'priority'));
      update_query('tbladdonmodules',array('value' => $_POST['close_ticket']), array('module'=>'sirportly', 'setting' => 'close_ticket'));
      update_query('tbladdonmodules',array('value' => $_POST['closed_status']), array('module'=>'sirportly', 'setting' => 'closed_status'));
      echo '<div class="successbox"><strong>Success!</strong><br />Changes saved successfully.</div>';
    }

    $sirportly_settings = sirportly_settings();
    $brands = sirportly_brands($vars['token'],$vars['secret']);
    $status = sirportly_status($vars['token'],$vars['secret']);
    $priority = sirportly_priorities($vars['token'],$vars['secret']);


echo '<p>By selecting a brand below all tickets opened via the client area will be submitted to Sirportly, <strong><u>NOT</u></strong> WHMCS, keep "Disabled" selected if you want to keep tickets within WHMCS.</p>
  <form method="POST" action="addonmodules.php?module=sirportly&action=support">
    <table class="form" width="100%" border="0" cellspacing="2" cellpadding="3">
      <tr>
        <td width="20%" class="fieldlabel">Brand</td>
        <td class="fieldarea"><select name="brand">';
          foreach ($brands as $key => $value) {
            echo '<option'.($sirportly_settings['brand'] == $key ? ' selected=selected':'').' value="'.$key.'">'.$value.'</option>';
          }
echo '
       </select> </td>
      </tr>

      <tr>
        <td width="20%" class="fieldlabel">New Ticket Status</td>
        <td class="fieldarea"><select name="status">';
          foreach ($status as $key => $value) {
            echo '<option'.($sirportly_settings['status'] == $key ? ' selected=selected':'').' value="'.$key.'">'.$value.'</option>';
          }

echo '</select></td>
      </tr>

      <tr>
        <td width="20%" class="fieldlabel">Default Ticket Priority</td>
        <td class="fieldarea"><select name="priority">';
          foreach ($priority as $key => $value) {
            echo '<option'.($sirportly_settings['priority'] == $value['id'] ? ' selected=selected':'').' value="'.$value['id'].'">'.$value['name'] .'</option>';
          }

echo ' </select></td>
      </tr>
      <tr>
        <td width="20%" class="fieldlabel">Allow Clients to Close Tickets</td>
        <td class="fieldarea"><select name="close_ticket">
          <option'.($sirportly_settings['close_ticket'] == '1' ? ' selected=selected':'').' value="1">Yes</option>
          <option'.($sirportly_settings['close_ticket'] == '0' ? ' selected=selected':'').' value="0">No</option>
          </select>
        </td>
      </tr>
      <tr>
        <td width="20%" class="fieldlabel">Closed Ticket Status</td>
        <td class="fieldarea"><select name="closed_status">';
          foreach ($status as $key => $value) {
            echo '<option'.($sirportly_settings['closed_status'] == $key ? ' selected=selected':'').' value="'.$key.'">'.$value.'</option>';
          }
        echo '</select></td>
      </tr>
    </table>
    <p align="center"><input type="submit" value="Save Changes" /></p>
  </form>';

    break;

    default:

      if ($_POST) {
        unset($_POST['token']);
        mysql_query('TRUNCATE TABLE `sirportly`');
        foreach ($_POST as $group_key => $group_value) {
          foreach ($group_value as $row_key => $row_value) {
            insert_query('sirportly',array('field_type' => $group_key, 'field_name' => $row_key));
          }
        }
        echo '<div class="infobox"><strong>Changes Saved Successfully!</strong><br />Your changes have been saved.</div>';
      }


      $current_fields = array();
      $result = mysql_query("SELECT * FROM `sirportly`");
      while ($row = mysql_fetch_array($result, MYSQL_NUM)) {
        $current_fields[$row['1']][$row[2]] = $row[2];
      }

      $tables = array('tblclients','tbldomains','tblhosting','tblinvoices');
      $fields = array();
      foreach ($tables as $key => $value) {
        $result = mysql_query("SHOW COLUMNS FROM `".$value."`");
        while ($row = mysql_fetch_array($result, MYSQL_NUM)) {
          $fields[$value][$row[0]] = $row[0];
        }
      }

      unset($fields['tblclients']['email'],$fields['tblclients']['pwresetexpiry'],$fields['tblclients']['pwresetkey'],$fields['tbldomains']['userid'],$fields['tblhosting']['userid']);
      unset($fields['tblinvoices']['userid']);
      unset($fields['tblclients']['password']);


      echo '<form method="POST" action="addonmodules.php?module=sirportly">
      <table class="form" width="100%" border="0" cellspacing="2" cellpadding="3">
        <tr>';

          foreach ($fields as $group_key => $group_value) {
            echo '<td width="20%" class="fieldlabel">'.sirportly_lang($group_key).' fields</td><td class="fieldarea">';
            foreach ($group_value as $row_key => $row_value) {
             if (in_array($row_value,$current_fields[$group_key])) {
                echo '<label><input type="checkbox" checked name="'.$group_key.'['.$row_value.']" value="1" /> '.sirportly_lang($row_value).'</label>';
              } else {
                echo '<label><input type="checkbox" name="'.$group_key.'['.$row_value.']" value="1" /> '.sirportly_lang($row_value).'</label>';
              }
          }
          echo '</td></tr>';
      }
      echo '</table><p align="center"><input type="submit" value="Save Changes" /></p></form>';

    break;
  }


}

function sirportly_lang($text){
  switch ($text) {
    case 'tblclients': return 'Client'; break;
    case 'tbldomains': return 'Domain'; break;
    case 'tblhosting': return 'Service'; break;
    case 'tblinvoices': return 'Invoice'; break;

    case 'id': return 'ID'; break;
    case 'firstname': return 'First Name'; break;
    case 'lastname': return 'Last Name'; break;
    case 'companyname': return 'Company Name'; break;
    case 'email': return 'Email'; break;
    case 'address1': return 'Address 1'; break;
    case 'address2': return 'Address 2'; break;
    case 'city': return 'City'; break;
    case 'state': return 'State'; break;
    case 'postcode': return 'Post Code'; break;
    case 'country': return 'Country'; break;
    case 'orderid': return 'Order ID'; break;
    case 'packageid': return 'Package ID'; break;
    case 'server': return 'Server'; break;
    case 'regdate': return 'Registration Date'; break;
    case 'registrationdate': return 'Registration Date'; break;
    case 'type': return 'Type'; break;
    case 'invoicenum': return 'Invoice Number'; break;
    case 'domain': return 'Domain'; break;
    case 'paymentmethod': return 'Payment Method'; break;
    case 'date': return 'Date'; break;
    case 'duedate': return 'Due Date'; break;
    case 'phonenumber': return 'Phone Number'; break;
    case 'currency': return 'Currency'; break;
    case 'currencydefaultgateway': return 'Default Gateway'; break;
    case 'credit': return 'Credit'; break;
    case 'taxexempt': return 'Tax Exempt'; break;
    case 'latefeeoveride': return 'Late Fee Override'; break;
    case 'overideduenotices': return 'Override Due Notices'; break;
    case 'separateinvoices': return 'Seperate Invoices'; break;
    case 'disableautocc': return 'Disable Auto CC'; break;
    case 'datecreated': return 'Date Created'; break;
    case 'notes': return 'Notes'; break;
    case 'billingcid': return 'Billing CID'; break;
    case 'securityqid': return 'Security Question'; break;
    case 'securityqans': return 'Security Answer'; break;
    case 'groupid': return 'Group ID'; break;
    case 'cardtype': return 'Card Type'; break;
    case 'cardlastfour': return 'Card Last Four'; break;
    case 'cardnum': return 'Card Number'; break;
    case 'startdate': return 'Start Date'; break;
    case 'expdate': return 'Expiration Date'; break;
    case 'issuenumber': return 'Issue Number'; break;
    case 'bankname': return 'Bank Name'; break;
    case 'banktype': return 'Bank Type'; break;
    case 'bankcode': return 'Bank Code'; break;
    case 'bankacct': return 'Bank Account'; break;
    case 'gatewayid': return 'Gateway ID'; break;
    case 'lastlogin': return 'Last Login'; break;
    case 'ip': return 'IP'; break;
    case 'host': return 'Host'; break;
    case 'status': return 'Status'; break;
    case 'language': return 'Language'; break;
    case 'firstpaymentamount': return 'First Payment Amount'; break;
    case 'recurringamount': return 'Recurring Amount'; break;
    case 'registrar': return 'Registrar'; break;
    case 'registrationperiod': return 'Registration Period'; break;
    case 'datepaid': return 'Date Paid'; break;
    case 'subtotal': return 'Subtotal'; break;
    case 'tax': return 'Tax'; break;
    case 'tax2': return 'Tax 2'; break;
    case 'expirydate': return 'Expiry Date'; break;
    case 'subscriptionid': return 'Subscription ID'; break;
    case 'promoid': return 'Promotion ID'; break;
    case 'nextduedate': return 'Next Due Date'; break;
    case 'nextinvoicedate': return 'Next Invoice Date'; break;
    case 'additionalnotes': return 'Notes'; break;
    case 'dnsmanagement': return 'DNS Management'; break;
    case 'emailforwarding': return 'Email Forwarding'; break;
    case 'idprotection': return 'ID Protection'; break;
    case 'donotrenew': return 'Do Not Renew'; break;
    case 'amount': return 'Amount'; break;
    case 'billingcycle': return 'Billing Cycle'; break;
    case 'domainstatus': return 'Status'; break;
    case 'username': return 'Username'; break;
    case 'password': return 'Password'; break;
    case 'suspendreason': return 'Suspend Reason'; break;
    case 'overideautosuspend': return 'Overide Auto Suspend'; break;
    case 'overidesuspenduntil': return 'Override Suspend Until'; break;
    case 'dedicatedip': return 'Dedicated IP'; break;
    case 'assignedips': return 'Assigned IPs'; break;
    case 'ns1': return 'Nameserver One'; break;
    case 'ns2': return 'Nameserver Two'; break;
    case 'diskusage': return 'Disk Usage'; break;
    case 'disklimit': return 'Disk Limit'; break;
    case 'bwlimit': return 'Bandwidth Limit'; break;
    case 'bwusage': return 'Bandwidth Usage'; break;
    case 'lastupdate': return 'Last Update'; break;
    case 'total': return 'Total'; break;
    case 'taxrate': return 'Tax Rate'; break;
    case 'taxrate2': return 'Tax Rate 2'; break;
    case 'defaultgateway': return 'Default Gateway'; break;

    default: return '<i>'.$text.'</i>'; break;
  }
}

?>
