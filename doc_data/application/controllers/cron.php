<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class cron extends CI_Controller {
	var $param='';
    function __construct(){
		parent::__construct();
		$this->load->model('crawler');
		$this->load->controller('mn');
		$this->load->controller('il');
		$this->load->controller('oh');
		//$this->load->controller('fl');
		//$this->param='';
		
		

	}
	function index($param)
	{
	switch($param)
	 {
	 case "mn" :
	 $this->mn->index();
	 break;
	 
	 case "oh":
		$this->oh->index();
     break;
	 
	 case "il":
		$this->il->index();
     break;
	 default : 
	 }
	
		//$this->oh->index();
		//$this->fl->index();
		
	 /*if($this->state='mn')
	 {
		$this->mn->index();
	 }
	 if($this->state='il')
	 {
	   $this->il->index();
	 }
	 if($this->state='oh')
	 {
		$this->oh->index();
	 }*/
	 }
	 
	}
	 ?>