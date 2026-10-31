<?php
require 'config.php';
header('Content-Type: application/json; charset=utf-8');
function out($a){echo json_encode($a);exit;}
function fail($m){http_response_code(400);out(['error'=>$m]);}
if(empty($_SESSION['uid'])){http_response_code(401);out(['error'=>'Sessão expirada']);}
$uid=(int)$_SESSION['uid']; $d=db(); $a=$_POST['a']??'state';

if($a==='invest'||$a==='sell'){
  $sym=$_POST['sym']??''; $amt=round((float)($_POST['amount']??0),2);
  $d->begin_transaction();
  $u=q("SELECT balance FROM users WHERE id=? FOR UPDATE",'i',$uid)[0];
  $as=q("SELECT price FROM assets WHERE sym=?",'s',$sym);
  if(!$as){$d->rollback();fail('Activo inválido');}
  $price=$as[0]['price'];
  if($a==='invest'){
    if($amt<10000){$d->rollback();fail('O investimento mínimo é de 10.000 Kz');}
    if($amt>$u['balance']+0.001){$d->rollback();fail('Saldo insuficiente');}
    q("UPDATE users SET balance=balance-? WHERE id=?",'di',$amt,$uid);
    q("INSERT INTO holdings VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE units=units+VALUES(units),invested=invested+VALUES(invested)",'isdd',$uid,$sym,$amt/$price,$amt);
  }else{
    $h=q("SELECT units,invested FROM holdings WHERE user_id=? AND sym=?",'is',$uid,$sym);
    if(!$h){$d->rollback();fail('Não tens este activo');}
    $val=$h[0]['units']*$price;
    if($amt>$val&&$amt<=$val*1.02)$amt=$val; // tolerância: o preço mexe entre abrir o modal e confirmar
    if($amt<=0||$amt>$val+0.01){$d->rollback();fail('Valor de venda inválido');}
    $f=min(1,$amt/$val);
    if($f>0.9999){q("DELETE FROM holdings WHERE user_id=? AND sym=?",'is',$uid,$sym);$amt=$val;}
    else q("UPDATE holdings SET units=units*(1-?),invested=invested*(1-?) WHERE user_id=? AND sym=?",'ddis',$f,$f,$uid,$sym);
    q("UPDATE users SET balance=balance+? WHERE id=?",'di',$amt,$uid);
  }
  $d->commit();
}

// Mercado simulado: variação aleatória a cada ~2s (volatilidade por categoria)
q("UPDATE assets SET price=price*(1+(RAND()-0.498)*2*CASE cat WHEN 'cripto' THEN .012 WHEN 'bolsa' THEN .005 WHEN 'imoveis' THEN .001 ELSE .0004 END),upd=NOW() WHERE upd<NOW()-INTERVAL 2 SECOND");

out([
  'user'=>q("SELECT name,balance FROM users WHERE id=?",'i',$uid)[0],
  'assets'=>q("SELECT sym,name,cat,price,open_price FROM assets ORDER BY FIELD(cat,'cripto','tesouro','imoveis','bolsa'),sym"),
  'holdings'=>q("SELECT sym,units,invested FROM holdings WHERE user_id=?",'i',$uid)
]);
