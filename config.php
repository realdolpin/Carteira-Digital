<?php
// Kwanza Card - configuração (Apache :8080, MySQL :3308)
session_start();
const DB_HOST='127.0.0.1', DB_PORT=3308, DB_USER='root', DB_PASS='', DB_NAME='kwanza_card';

function db(){
  static $d; if($d) return $d;
  mysqli_report(MYSQLI_REPORT_OFF);
  $d=new mysqli(DB_HOST,DB_USER,DB_PASS,'',DB_PORT);
  if($d->connect_error) die('Erro ao ligar ao MySQL (porta '.DB_PORT.'): '.$d->connect_error);
  $d->set_charset('utf8mb4');
  $d->query("CREATE DATABASE IF NOT EXISTS ".DB_NAME." CHARACTER SET utf8mb4");
  $d->select_db(DB_NAME);
  $d->query("CREATE TABLE IF NOT EXISTS users(id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(80),email VARCHAR(120) UNIQUE,pass VARCHAR(255),balance DOUBLE DEFAULT 300000,created DATETIME DEFAULT CURRENT_TIMESTAMP)");
  $d->query("CREATE TABLE IF NOT EXISTS assets(sym VARCHAR(12) PRIMARY KEY,name VARCHAR(60),cat VARCHAR(10),price DOUBLE,open_price DOUBLE,upd DATETIME DEFAULT CURRENT_TIMESTAMP)");
  $d->query("CREATE TABLE IF NOT EXISTS holdings(user_id INT,sym VARCHAR(12),units DOUBLE,invested DOUBLE,PRIMARY KEY(user_id,sym))");
  $d->query("CREATE TABLE IF NOT EXISTS transfers(id INT AUTO_INCREMENT PRIMARY KEY,from_id INT,to_id INT,sym VARCHAR(12),units DOUBLE,value DOUBLE,created DATETIME DEFAULT CURRENT_TIMESTAMP)");
  if(!$d->query("SELECT 1 FROM assets LIMIT 1")->num_rows){
    $seed=[ // símbolo, nome, categoria, preço em Kz (simulado)
      ['BTC','Bitcoin','cripto',95000000],['ETH','Ethereum','cripto',3200000],
      ['BNB','BNB','cripto',620000],['SOL','Solana','cripto',165000],
      ['OT-3A','Obrigação do Tesouro 3 anos','tesouro',10000],['BT-182','Bilhete do Tesouro 182 dias','tesouro',10000],
      ['TLT','Fundo Imobiliário Talatona','imoveis',50000],['KLM','Fundo Imobiliário Kilamba','imoveis',35000],
      ['BAI','Acções Banco Angolano de Investimentos','bolsa',8500],['BFA','Acções Banco de Fomento Angola','bolsa',12000],
      ['SBA','Acções Standard Bank Angola','bolsa',6200]];
    foreach($seed as $s) q("INSERT INTO assets(sym,name,cat,price,open_price) VALUES(?,?,?,?,?)",'sssdd',$s[0],$s[1],$s[2],$s[3],$s[3]);
  }
  return $d;
}
// Consulta preparada: devolve linhas (SELECT) ou [] (outros)
function q($sql,$types='',...$p){
  $st=db()->prepare($sql); if($p) $st->bind_param($types,...$p);
  $st->execute(); $r=$st->get_result();
  return $r? $r->fetch_all(MYSQLI_ASSOC):[];
}
