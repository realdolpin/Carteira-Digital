<?php
require 'config.php';
if(!empty($_SESSION['uid'])){header('Location: dashboard.php');exit;}
$err=''; $mode=$_POST['mode']??'login';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $email=strtolower(trim($_POST['email']??'')); $pass=$_POST['pass']??''; $name=trim($_POST['name']??'');
  if($mode==='register'){
    if(strlen($name)<2||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($pass)<6) $err='Preenche o nome, um e-mail válido e uma palavra-passe com 6+ caracteres.';
    elseif(q("SELECT id FROM users WHERE email=?",'s',$email)) $err='Este e-mail já tem conta. Entra com a tua palavra-passe.';
    else{
      q("INSERT INTO users(name,email,pass,balance) VALUES(?,?,?,300000)",'sss',$name,$email,password_hash($pass,PASSWORD_DEFAULT));
      $_SESSION['uid']=db()->insert_id; header('Location: dashboard.php'); exit;
    }
  }else{
    $u=q("SELECT id,pass FROM users WHERE email=?",'s',$email);
    if($u&&password_verify($pass,$u[0]['pass'])){$_SESSION['uid']=$u[0]['id'];header('Location: dashboard.php');exit;}
    $err='E-mail ou palavra-passe incorrectos.';
  }
}
?><!doctype html><html lang="pt"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Kwanza Card</title><link rel="stylesheet" href="style.css"></head>
<body class="auth"><main class="auth-box">
  <div class="card3d"><span class="chip"></span><b>Kwanza Card</b><small>300.000 Kz de boas-vindas</small></div>
  <h1><?= $mode==='register'?'Abre a tua carteira':'Bem-vindo de volta' ?></h1>
  <p class="muted"><?= $mode==='register'?'Recebes 300.000 Kz para começar a investir.':'Entra para ver os teus investimentos.' ?></p>
  <?php if($err): ?><div class="alert"><?= htmlspecialchars($err) ?></div><?php endif; ?>
  <form method="post"><input type="hidden" name="mode" value="<?= $mode==='register'?'register':'login' ?>">
    <?php if($mode==='register'): ?><label>Nome<input name="name" required autocomplete="name"></label><?php endif; ?>
    <label>E-mail<input type="email" name="email" required autocomplete="email"></label>
    <label>Palavra-passe<input type="password" name="pass" required minlength="6"></label>
    <button class="btn"><?= $mode==='register'?'Criar conta':'Entrar' ?></button>
  </form>
  <p class="muted sw"><?= $mode==='register'?'Já tens conta?':'Ainda não tens conta?' ?>
    <form method="post"><input type="hidden" name="mode" value="<?= $mode==='register'?'login':'register' ?>"><button class="link"><?= $mode==='register'?'Entrar':'Criar conta' ?></button></form></p>
</main></body></html>
