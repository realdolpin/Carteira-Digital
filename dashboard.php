<?php
require 'config.php';
if(isset($_GET['logout'])){session_destroy();header('Location: index.php');exit;}
if(empty($_SESSION['uid'])){header('Location: index.php');exit;}
?><!doctype html><html lang="pt"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Kwanza Card</title><link rel="stylesheet" href="style.css"></head>
<body>
<header class="top"><div class="logo"><span class="chip"></span>Kwanza Card</div>
  <div><span id="uname" class="muted"></span> <a class="link" href="?logout=1">Sair</a></div></header>
<main class="wrap">
  <section class="hero">
    <div><p class="muted">Património total</p><h2 id="total">—</h2><p id="pl" class="muted"></p></div>
    <div class="donut" id="donut"><div><small>Investido</small><b id="invPct">0%</b></div></div>
    <div class="legend" id="legend"></div>
  </section>
  <section class="stats">
    <div><p class="muted">Saldo disponível</p><b id="bal"></b></div>
    <div><p class="muted">Valor investido hoje</p><b id="val"></b></div>
    <div><p class="muted">Lucro / perda</p><b id="gain"></b></div>
  </section>
  <nav class="tabs" id="tabs">
    <button data-t="market" class="on">Mercado</button><button data-t="port">Minha carteira</button><button data-t="hist">Transferências</button><button data-t="gloss">Glossário</button>
  </nav>
  <section id="market"><div class="chips" id="chips"></div><div id="list"></div></section>
  <section id="port" hidden><div id="plist"></div></section>
  <section id="hist" hidden><div id="hlist"></div></section>
  <section id="gloss" hidden><dl id="gl"></dl></section>
</main>
<div class="modal" id="modal" hidden><form id="mf">
  <h3 id="mt"></h3><p class="muted" id="ms"></p>
  <label id="emL" hidden>E-mail do destinatário<input id="em" type="email" autocomplete="off"></label>
  <label>Valor em Kz<input id="amt" type="number" step="100" required></label>
  <div class="quick" id="quick"></div><p class="muted" id="mh"></p>
  <div class="row"><button type="button" class="btn ghost" id="mc">Cancelar</button><button class="btn" id="mb"></button></div>
</form></div>
<div class="toast" id="toast"></div>
<script src="app.js"></script></body></html>
