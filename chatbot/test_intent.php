<?php session_start(); $_SESSION['user_id']=1; $_SESSION['role_id']=2; $_POST['query']='what is my current due bill?'; require 'intent_router.php';
