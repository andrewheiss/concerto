<form method="POST" action="<?=ADMIN_URL?>/screens/update/<?=$this->screen->id?>">
<?php 
	include("_form.php");
?>
<input value="Save Changes" type="submit" name="submit" />
</form>