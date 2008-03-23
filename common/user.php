<?
/*
Class: User
Status: Mild, like salsa
Functionality:  Create users, allows access to user properties, group stuff, etc
       create_user         		Creates a new user

        set_properties         	Writes user properties back to the database

        add_to_group	        	Adds current user to a group

        remove_from_group       	Removes the user from a group

        in_group			Tests to see if a user is in a group
		
        list_groups			Lists all the groups a user is in		
		
        can_write			Tests to see if a user can write to an object (essentially a permission check)

Comments: 
	can_write is my basic implementation of 'privledges', essentially it combines owner + group to test if the user is an owner who can write
  
*/
class User{
	var $id;
	var $username;
	var $name;
	var $firstname;
	var $email;
	var $admin_privileges;
	
	var $groups = array();
	var $set;
		
	function __construct($username_in = ''){
		if($username_in != ''){
         if(is_numeric($username_in))
            $sql = "SELECT * FROM user WHERE id = '$username_in' LIMIT 1";
         else
            $sql = "SELECT * FROM user WHERE username = '$username_in' LIMIT 1";
			$res = sql_query($sql);
			if($res != 0){
				$data = (sql_row_keyed($res,0));
				$this->id = $data['id'];
				$this->username = $data['username'];
				$this->name = $data['name'];
				$this->email = $data['email'];
				$this->admin_privileges = $data['admin_privileges'];
				
				//Get firstname for aesthetic output
				$namesplit = split(" ",$this->name);
				$this->firstname = $namesplit[0]; 

				//Find groups the user belongs to
				$sql1 = "SELECT group_id FROM user_group WHERE user_id = $this->id";
				$res1 = sql_query($sql1);
				if($res1 != 0){
					$i = 0;
					while($row = sql_row_keyed($res1, $i)){
						$this->groups[] = $row['group_id'];
						$i++;
					}
				}
				//End group block
				
				$this->set = true;
				return true;
			} else {
				return false;
			}	
		
		} else {
			$this->set = false;
			return true;
		}
	}
	
	//Creates a user
	function create_user($username_in, $name_in, $email_in, $admin_privileges_in){
		if($this->set == true){
			return false; //We already have a user object you idiot
		} else {
			//Cleaning Block
			$username_in = escape($username_in);
			$name_in = escape($name_in);
			$email_in = escape($email_in);
			if(!is_numeric($admin_privileges_in)){
				return false;
			}
			//End testing/cleaning block
			
			$sql = "INSERT INTO user (username, name, email, admin_privileges) VALUES ('$username_in', '$name_in', '$email_in', $admin_privileges_in)";
			
			$res = sql_query($sql);
			if($res){
				$sql_id = sql_insert_id();
				
				$this->id = $sql_id;
				$this->username = stripslashes($username_in);
				$this->name = stripslashes($name_in);
				$this->email = stripslashes($email_in);
				$this->admin_privileges = $admin_privileges_in;
				
				//Get firstname for aesthetic output
				$namesplit = split(" ",$this->name);
				$this->firstname = $namesplit[0]; 

				$this->set = true;
	
				$notify = new Notification();
	                        $notify->notify('user', $this->id, '', '', 'new');

				return true;
			} else {
				return false;
			}
			
		}
	
	}
	
	//Sets their properties back to the database
	function set_properties(){
		$sql = "UPDATE user SET username = '$this->username', name = '$this->name', email = '$this->email', admin_privileges = '$this->admin_privileges' WHERE id = $this->id LIMIT 1";
		$res = sql_query($sql);
		if($res){
			$notify = new Notification();
                        $notify->notify('user', $this->id, 'user', $_SESSION['user']->id, 'update');

			return true;
		} else {
			return false;
		}
	
	}
	//Adds a person to a group
	function add_to_group($group_id){
		if(!in_array($group_id, $this->groups)){
			$sql = "INSERT INTO user_group (user_id, group_id) VALUES ($this->id, $group_id)";
			$res = sql_query($sql);
			if($res != 0){
				$this->groups[] = $group_id;
	
				$notify = new Notification();
	                        $notify->notify('group', $group_id, 'user', $this->id, 'join');

				return true;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}
	//Removes a person from a group
	function remove_from_group($group_id){
		if(in_array($group_id, $this->groups)){
			$sql = "DELETE FROM user_group WHERE user_id = $this->id AND group_id = $group_id LIMIT 1";
			$res = sql_query($sql);
			if($res != 0){
				$key = array_search($group_id, $this->groups);
				unset($this->groups[$key]);

				$notify = new Notification();
	                        $notify->notify('group', $group_id, 'user', $this->id, 'leave');

				return true;
			} else {
				return false;
			}
		} else {
			return true;
		}
	}
	
	//Tests to see if a person is part of a group
	function in_group($group_id){
		if(in_array($group_id, $this->groups)){
			return true;
		} else {
			return false;
		}
	}
	
	//Lists all the groups a user is in
	function list_groups(){
		foreach($this->groups as $group_id){
			$data[] = new Group($group_id);
		}
		return $data;
	}
	
	//Checks if a user should have access to write/modify an existing object
	function can_write($type, $item_id){
		//The admin override
		if($this->admin_privileges){
			return true;
		}
		//Feed Test
		if($type == 'feed'){
			$sql = "SELECT group_id FROM feed WHERE id = $item_id";
			$res = sql_query($sql);
			if($res != 0){
				$data = (sql_row_keyed($res,0));
				$group_id = $data['group_id'];
				return $this->in_group($group_id);
			} else {
				return false;
			}
		//Content Test
		} else if($type == 'content'){
			$sql = "SELECT user_id FROM content WHERE id = $item_id";
			$res = sql_query($sql);
			if($res != 0){
				$data = (sql_row_keyed($res,0));
				$user_id = $data['user_id'];
				if($this->id == $user_id){
					return true;
				} else {
					return false;
				}
			} else {
				return false;
			}
		//Screen Test
		} else if($type == 'screen'){
			$sql = "SELECT group_id FROM screen WHERE mac_address = $item_id";
			$res = sql_query($sql);
			if($res != 0){
				$data = (sql_row_keyed($res,0));
				$group_id = $data['group_id'];
				return $this->in_group($group_id);
			} else {
				return false;
			}
		} else if($type == 'group'){
            return $this->in_group($item_id);
      }
	}
}
?>