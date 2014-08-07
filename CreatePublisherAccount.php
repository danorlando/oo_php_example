<?php
include("includes/local/db_config.php");


class CreatePublisherAccount extends AMFBase {
	
/** The CreatePublisherAccount class holds the methods necessary for creating a new publisher account as well as sending a forgotten password, and enabling a trial period.  
  * The function CreatePublisherAccount is the class constructor and is responsible for opening the connection to the database. The DB connection parameters are abstracted in the <code>AMFBase  	
  *	</code> class
  */
	function CreatePublisherAccount()
	{
      $this->conn = mysql_pconnect($this->dbhost, $this->dbuser, $this->dbpass);
      mysql_select_db ($this->dbname);
    }

		/** This method is called when the first form in the publisher signup process is submitted. It is responsible for accepting name, address, city, state, zip, phone, and email as arguments and inserts them with a new publisher record in the database. It then sets a cookie on the user's machine, which lasts for 30 days.
		  * 
		  * @param $pub_name Corresponds to name input form field
		  * @param $pub_billing_address1 The address input
		  * @param $pub_billing_address2 Address 2nd line input, currently not in use to save space on the form
		  * @param $pub_billing_city Publisher's billing city
		  * @param $pub_billing_state Publisher's billing state
		  * @param $pub_billing_zip The publisher's billing zip code (currently U.S. ONLY)
		  * @param $pub_phone The publisher's phone number
		  * @param $pub_email Publisher's email address; NOTE: this is also used as the username for logging into the control panel
		  *
		  * @returns 
		  *
		  */
		function insertBasicInfo($pub_name,$pub_billing_address1,$pub_billing_address2,$pub_billing_city,$pub_billing_state,$pub_billing_zip,$pub_phone,$pub_email)
		{
			$dt=date("Y-m-d:h:i:s");
			$vid=mysql_query("insert into avijax_publisher(first_name,address,address2,city,state,postcode,phone,email,added_on)values('".addslashes($pub_name)."', '".addslashes($pub_billing_address1)."', '".addslashes($pub_billing_address2)."','".$pub_billing_city."', '".$pub_billing_state."','".$pub_billing_zip."','".$pub_phone."','".$pub_email."','".$dt."')");
			$publisherid=mysql_insert_id();
			setcookie("publisher_id", $publisherid);
			 
			return $vid;
		}


		/** This method is called when the user selects the submit button on the create password form in the creation of a new publisher TRIAL account. The reason we use a separate method here is because this method is also responsible for setting the login status to 5 in the database, which corresponds to a publisher on a trial membership. This method also sets the password in a cookie for later reference.
		  * 
		  * @returns 
		  *
		  * @param $password The password entered into the respective form field in the account creation form.
		  */
		function createTrialPassword($password)
		{
			setcookie("pass", $password);
			$dt=date("Y-m-d",mktime(0, 0, 0, date("m")  , date("d")+30, date("Y")));
			$vid3=mysql_query("Update avijax_publisher set password='".md5($password)."',login_status='5',exp_date='".$dt."' where publisher_id='".$_COOKIE[publisher_id]."'");
		
			return $vid3;
		}


		/** This method is called when the submit button on the credit card info form of the publisher signup process is selected. It receives all credit card info values as arguments, sends them along with the any other necessary previously entered values to the payment gateway through the Authorize.net API for authorization, and if the response returned from Authorize.net is that the info passed authorization, the method sends a thank you email to the new publisher and returns <code>true</code> back to the client interface. If the response from the gateway is false however, the email is not sent and the response of <code>false</code> is returned to the client.
		  * 
		  * @returns Boolean : The response from Authorize.net specifying whether or not the payment information provided is any good.
		  *
		  * @param $pub_cc_owner
		  * @param $cc_type
		  * @param $cc_number
		  * @param $cc_exp_month
		  * @param $cc_exp_year
		  *	@param $cc_ccv
		  */
		function insertPaymentInfo($pub_cc_owner,$cc_type,$cc_number,$cc_exp_month,$cc_exp_year,$cc_ccv)
		{
			$totalamount=50;
			
			$DEBUGGING					= 1;				# Display additional information to track down problems
			$TESTING					= 1;				# Set the testing flag so that transactions are not live
			$ERROR_RETRIES				= 2;				# Number of transactions to post if soft errors occur
			
			$auth_net_login_id			= "6qHf35CB";
			$auth_net_tran_key			= "42Bjsv7ZCDS33j4v";
			#$auth_net_url				= "https://test.authorize.net/gateway/transact.dll";
			#  Uncomment the line ABOVE for test accounts or BELOW for live merchant accounts
			$auth_net_url				= "https://secure.authorize.net/gateway/transact.dll";
			$year = substr($cc_exp_year, -2);  
			
			$expdt=$cc_exp_month.$year;
			$authnet_values				= array
			(
				"x_login"				=> $auth_net_login_id,
				"x_version"				=> "3.1",
				"x_delim_char"			=> "|",
				"x_delim_data"			=> "TRUE",
				"x_url"					=> "FALSE",
				"x_type"				=> "AUTH_CAPTURE",
				"x_method"				=> "CC",
				"x_tran_key"			=> $auth_net_tran_key,
				"x_relay_response"		=> "FALSE",
				"x_card_num"			=> $cc_number,
				"x_exp_date"			=> $expdt,
				"x_description"			=> "Avijax",
				"x_amount"				=> $totalamount,
				"x_first_name"			=> $pub_cc_owner,
				"x_last_name"			=> $pub_cc_owner,
				"x_address"				=> "address",
				"x_city"				=> "city",
				"x_state"				=> "state",
				"x_test_request"        => "FALSE",
				"x_zip"					=> "zip",
				"SpecialCode"			=> "Avijax",
			);
			
			$fields = "";
			foreach( $authnet_values as $key => $value ) $fields .= "$key=" . urlencode( $value ) . "&";
			
			#$ch = curl_init("https://test.authorize.net/gateway/transact.dll"); 
			###  Uncomment the line ABOVE for test accounts or BELOW for live merchant accounts
			$ch = curl_init("https://secure.authorize.net/gateway/transact.dll"); 
			curl_setopt($ch, CURLOPT_HEADER, 0); // set to 0 to eliminate header info from response
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // Returns response data instead of TRUE(1)
			curl_setopt($ch, CURLOPT_POSTFIELDS, rtrim( $fields, "& " )); // use HTTP POST to send form data
			### curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // uncomment this line if you get no gateway response. ###
			$resp = curl_exec($ch); //execute post and get results
			curl_close ($ch);
			$result=explode("|",$resp);
			
			if($result[0]=='1' and $result[1]=='1' and $result[2]=='1')  {
				$vid1=mysql_query("Update avijax_publisher set cc_owner='".addslashes($pub_cc_owner)."', card_type='".addslashes($cc_type)."', cc_number='".addslashes($cc_number)."',cc_expires_month='".$cc_exp_month."', cc_expires_year='".$cc_exp_year."',ccv_number='".$cc_ccv."' where publisher_id='".$_COOKIE[publisher_id]."'");
				
				//insert the due date and subscription amount in the database
				$dt=date("Y-m-d",mktime(0, 0, 0, date("m")  , date("d")+30, date("Y")));
				$ins=mysql_query("insert into avijax_publisher_payment_due(publisher_id,due_date)values('".$_COOKIE[publisher_id]."','".$dt."')");
				
				//insert the values in payment table
				$today=date("Y-m-d");
				$inss=mysql_query("insert into avijax_publisher_payment(publisher_id,payment,pay_date)values('".$_COOKIE[publisher_id]."','50','".$today."')");
				
				//update login status
				$vid1=mysql_query("Update avijax_publisher set login_status='1',exp_date='".$dt."' where publisher_id='".$_COOKIE[publisher_id]."'");
				
				//select publisher name and email id
				$name=mysql_query("select first_name,last_name,email from avijax_publisher where publisher_id='".$_COOKIE[publisher_id]."'");
				$pub_name=mysql_fetch_array($name);
				
				// send registration confirmation mail to publisher
				$mesg='Dear '.ucfirst($pub_name[0]).' '.$pub_name[1].', 
				
				Thank you for registering your Avijax video publisher membership. Your account information is listed below. 
				
				Avijax account username: ('.$pub_name[2].')
				Avijax account password: ('.$_COOKIE[pass].')
				
				NOTE: Your Avijax account password is also your personal "master password", which may be used for logging into any of your customer\'s accounts should they require customer support from you or if you would like to see how a video looks from the customer\'s perspective. If you have any questions, please feel free to contact us at support@avijax.com.
				
				
				Respectfully,
				
				The Avijax Support Team';
				
				$headers = 'From: info@avijax.com';
			
				mail($pub_name[2],"Avijax Publisher Account Registration Confirmation",$mesg,$headers);
				$_COOKIE[pass]="";
				
				return true;
			}
			else {
				//$val=false;
				return false;
			}
		
		}
		
		
		/** This method inserts the Email ID for the publisher's paypal account for PPV revenue deposits into the database. This method gets called when the submit button is selected from the paypal form during the publisher signup process. 
		  * @param $pub_paypal_id
		  * @returns
		  */
		function insertPaypalInfo($pub_paypal_id)
		{
			$vid2=mysql_query("Update avijax_publisher set paypal_user='".addslashes($pub_paypal_id)."' where publisher_id='".$_COOKIE[publisher_id]."'");
		 	return $vid2;
		}

		
	  /** Insets the publisher's password into the database during the signup process for the FULL MEMBER account type. This method is called specifically from the publisher signup form that pertains to full membership, as opposed to the trial account form. We use separate methods so the correct account status is set in the database. This method is therefore also responsible for setting the publisher's account status to 1, where it will remain for the initial 30 days of his or her membership before being switched to 2 by an automated cron script. This method also sets the user's password in a cookie for 30 days for later reference.
	    * TO DO: this function is NOT setting the login status to 1, as it should be...?
		* @returns
		* @param $password
		*/
		function createPassword($password)
		{
			setcookie("pass", $password);
			$vid3=mysql_query("Update avijax_publisher set password='".md5($password)."' where publisher_id='".$_COOKIE[publisher_id]."'");
			return $vid3;
		}

		/** insert mail info for customer....? I have absolutely NO idea what that means or what this method does.
		  * @returns
		  * @param $info
		  */
		function createMailinfo($info)
		{
		$vid3=mysql_query("Update avijax_publisher set pub_mailinfo='".addslashes($info)."' where publisher_id='".$_COOKIE[publisher_id]."'");
		
		 return $vid3;
		}

		/** Inserts company information into the database for the respective publisher. This method is called when the user selects submit from the company information form in the member signup process.
		  *
		  * @returns
		  * @param $company_name
		  * @param $domain_url
		  * @param $pub_cs_phone
		  * @param $pub_cs_email
		  */ 
		function insertCompanyInfo($company_name,$domain_url,$pub_cs_phone,$pub_cs_email)
		{
		$vid2=mysql_query("Update avijax_publisher set company_name='".addslashes($company_name)."', domain_url='".addslashes($domain_url)."', pub_cs_phone='".addslashes($pub_cs_phone)."',pub_cs_email='".$pub_cs_email."' where publisher_id='".$_COOKIE[publisher_id]."'");
		 return $vid2;
		}

		/** This method is called from the "forgot password" form. It's responsibility is to check to make sure the email ID provided exists for a publisher account currently stored in the database, and provided that it does, it generates a new password for the user and sends it to his or her respective email address. It then returns a Boolean value of <code>true</code> to the UI. If the email ID provided does not exist, it returns false to the UI so the client side interface can handle it.
		  * @returns Boolean : true if email id exists and new password was sent via email, false if there was no record found for the email id provided.
		  * @param $email The email id for the account that the password is being requested on
		  */
		function getForgotPass($email)
		{
			$vid3=mysql_query("Select * from avijax_publisher where email='".$email."'");
			$num=mysql_num_rows($vid3);
			
			//check if email id is correct then send the genrate password
			if($num > 0)
			{
				//generate random password
				$chars = "abcdefghijkmnopqrstuvwxyz023456789";
				
					srand((double)microtime()*1000000);
				
					$i = 0;
				
					$pass = '' ;
				
				
				
					while ($i <= 7) {
				
						$num = rand() % 33;
				
						$tmp = substr($chars, $num, 1);
				
						$pass = $pass . $tmp;
				
						$i++;
				
					}
				
				$value=mysql_fetch_array($vid3);
				$mesg="Your password is :    ".$pass."\n";
				$mesg.="If you did not request your password, please contact our fraud support department immediately at:  fraud@avijax.com";
				$headers = "From: Avijax <info@avijax.com>\n";
				
				//send password to publisher
				
				mail($email,"Password Request",$mesg,$headers);
				
				//update password in the table against the email id
				
				$vid3=mysql_query("Update avijax_publisher set password='".md5($pass)."' where email='".$email."'");
				 return true;
			 }
			 else {
				return false;
			 }
		}

		
		/** Sets the payment gateway to test of live mode for the publisher account. In other words, whether or not the customers should actually be charged for purchases.
		  * TO DO: Need to find out if this method is still relevant...?
		  */
		function setPaymentMode($mode)
		{
			$vid3=mysql_query("Update avijax_publisher set test_mode='".$mode."' where publisher_id='".$_COOKIE[publisher_id]."'");
		
			return $vid3;
		}


		/** Checks to see if the email address provided during the account signup process already exists for an account record in the database. 
		  * @returns Boolean : true if the email address is already present, false if it is not. Note that we are looking for a value of false in order for the user to continue in the publisher signup process.
		  * @param $email The email address that the method must check the database against.
		  */
		function checkEmailIdExists($email)
		{
			$vid3=mysql_query("Select publisher_id from avijax_publisher where email='".$email."'");
			$num3=mysql_num_rows($vid3);
			//check if email id is already exist
			if($num3 > 0) {
				return true;
			}
			else {
				return false;
			}
		}
  	
	 /** This method is responsible for getting the publisher_id value from the cookie stored on the user's machine, and upgrading the publisher's trial membership to full membership by changing the login_status field in the database from 5 (trial account) to 1 (full membership). The status remains at 1 until the initial 30-day period of the full membership has expired, at which point an automated cron script changes it to 2. 
	   * @returns
	   */ 
	 function upgradeMembership() 
	 {
		$pub=mysql_query("UPDATE avijax_publisher set login_status='1' where publisher_id='".$_COOKIE[publisher_id]."'");
		return $pub;
	 }
	 
	 /** Sets the publisher login status to 5, which means the publisher is on a trial membership. This method gets called when the user checks the trial membership checkbox on the signup form that is part of the avijax control panel, not the trial membership signup form.
	   * @returns
	   * @param
	   * @see
	  */
	 function enableTrialPeriod()
	 {
	 	$query=mysql_query("UPDATE avijax_publisher set login_status='5' where publisher_id='".$_COOKIE[publisher_id]."'");
		return $query;
	 }

	 
	 
}
?>