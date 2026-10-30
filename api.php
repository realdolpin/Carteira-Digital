<?php
require __DIR__.'/config.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

const SALDO_INICIAL = 300000;
const MIN_INVEST = 5000;

function gauss(): float {
    $u = 1 - mt_rand() / mt_getrandmax();
    $v = mt_rand() / mt_getrandmax();
    return sqrt(-2 * log($u)) * cos(2 * M_PI * $v);
}

// Simula o mercado: 1 passo aleatório por cada minuto decorrido
function tick(PDO $db): void {
    $now = time();
    foreach ($db->query("SELECT * FROM ativos")->fetchAll() as $a) {
        $n = intdiv($now - (int)$a['atualizado_em'], 60);
        if ($n < 1) continue;
        $capped = $n > 1440;
        $n = min($n, 1440);
        $novoTs = $capped ? $now : (int)$a['atualizado_em'] + $n * 60;
        $u = $db->prepare("UPDATE ativos SET atualizado_em=? WHERE id=? AND atualizado_em=?");
        $u->execute([$novoTs, $a['id'], $a['atualizado_em']]);
        if ($u->rowCount() === 0) continue;
        $p = (float)$a['preco']; $ts = (int)$a['atualizado_em'];
        $ins = $db->prepare("INSERT INTO historico (ativo_id,preco,ts) VALUES (?,?,?)");
        for ($i = 0; $i < $n; $i++) {
            $p *= max(0.5, 1 + $a['drift'] + $a['volatilidade'] * gauss());
            $ts += 60;
            if ($n - $i <= 120) $ins->execute([$a['id'], round($p, 8), $ts]);
        }
        $db->prepare("UPDATE ativos SET preco=? WHERE id=?")->execute([round($p, 8), $a['id']]);
    }
}

function estado(PDO $db, int $uid): array {
    $nome = $db->prepare("SELECT nome FROM utilizadores WHERE id=?"); $nome->execute([$uid]);
    $s = $db->prepare("SELECT saldo FROM carteira WHERE utilizador_id=?"); $s->execute([$uid]);
    $saldo = (float)$s->fetchColumn();
    $ativos = $db->query("SELECT * FROM ativos ORDER BY id")->fetchAll();
    $h = $db->prepare("SELECT preco FROM (SELECT preco, ts FROM historico WHERE ativo_id=? ORDER BY ts DESC LIMIT 40) t ORDER BY ts");
    $prox = PHP_INT_MAX;
    foreach ($ativos as &$a) {
        $h->execute([$a['id']]);
        $a['historico'] = array_map('floatval', array_column($h->fetchAll(), 'preco'));
        $a['preco'] = (float)$a['preco'];
        $a['variacao'] = ($a['preco'] / (float)$a['preco_inicial'] - 1) * 100;
        $prox = min($prox, (int)$a['atualizado_em'] + 60);
    }
    $q = $db->prepare("SELECT i.*, a.simbolo, a.nome, a.categoria, a.preco AS preco_atual
                       FROM investimentos i JOIN ativos a ON a.id=i.ativo_id WHERE i.utilizador_id=? ORDER BY i.id DESC");
    $q->execute([$uid]); $inv = $q->fetchAll();
    $totInv = 0; $totAtual = 0;
    foreach ($inv as &$i) {
        $i['valor'] = (float)$i['valor'];
        $i['valor_atual'] = $i['estado'] === 'ativo' ? (float)$i['unidades'] * (float)$i['preco_atual'] : (float)$i['valor_resgate'];
        $i['lucro'] = $i['valor_atual'] - $i['valor'];
        $i['lucro_pct'] = $i['valor'] > 0 ? $i['lucro'] / $i['valor'] * 100 : 0;
        if ($i['estado'] === 'ativo') { $totInv += $i['valor']; $totAtual += $i['valor_atual']; }
    }
    $pat = $saldo + $totAtual;
    // Regista um ponto do património por minuto para o gráfico
    $l = $db->prepare("SELECT MAX(ts) FROM patrimonio_hist WHERE utilizador_id=?"); $l->execute([$uid]);
    if (time() - (int)$l->fetchColumn() >= 60)
        $db->prepare("INSERT INTO patrimonio_hist (utilizador_id,valor,ts) VALUES (?,?,?)")->execute([$uid, round($pat, 2), time()]);
    $g = $db->prepare("SELECT valor, ts FROM (SELECT valor, ts FROM patrimonio_hist WHERE utilizador_id=? ORDER BY ts DESC LIMIT 120) t ORDER BY ts");
    $g->execute([$uid]);
    return [
        'nome' => $nome->fetchColumn(), 'agora' => time(), 'proxima' => $prox,
        'saldo' => $saldo, 'investido' => $totInv, 'valor_atual' => $totAtual,
        'lucro' => $totAtual - $totInv, 'patrimonio' => $pat,
        'patrimonio_hist' => array_map(fn($r) => ['v' => (float)$r['valor'], 't' => (int)$r['ts']], $g->fetchAll()),
        'ativos' => $ativos, 'investimentos' => $inv,
    ];
}

function erro(string $m, int $c = 400) { http_response_code($c); echo json_encode(['erro' => $m]); exit; }

try {
    $db = db();
    $acao = $_GET['a'] ?? 'estado';
    $post = $_SERVER['REQUEST_METHOD'] === 'POST';
    $in = json_decode(file_get_contents('php://input'), true) ?? [];

    // ----- Autenticação -----
    if ($acao === 'registar' && $post) {
        $nome = trim($in['nome'] ?? ''); $email = strtolower(trim($in['email'] ?? '')); $senha = $in['senha'] ?? '';
        if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) erro('Preencha o nome e um email válido.');
        if (strlen($senha) < 6) erro('A palavra-passe deve ter pelo menos 6 caracteres.');
        $e = $db->prepare("SELECT 1 FROM utilizadores WHERE email=?"); $e->execute([$email]);
        if ($e->fetchColumn()) erro('Este email já está registado.');
        $db->beginTransaction();
        $db->prepare("INSERT INTO utilizadores (nome,email,senha) VALUES (?,?,?)")->execute([$nome, $email, password_hash($senha, PASSWORD_DEFAULT)]);
        $uid = (int)$db->lastInsertId();
        $db->prepare("INSERT INTO carteira (utilizador_id,saldo) VALUES (?,?)")->execute([$uid, SALDO_INICIAL]);
        $db->commit();
        session_regenerate_id(true); $_SESSION['uid'] = $uid;
    } elseif ($acao === 'entrar' && $post) {
        $u = $db->prepare("SELECT id, senha FROM utilizadores WHERE email=?"); $u->execute([strtolower(trim($in['email'] ?? ''))]);
        $u = $u->fetch();
        if (!$u || !password_verify($in['senha'] ?? '', $u['senha'])) erro('Email ou palavra-passe incorrectos.');
        session_regenerate_id(true); $_SESSION['uid'] = (int)$u['id'];
    } elseif ($acao === 'sair') {
        session_destroy(); echo json_encode(['ok' => true]); exit;
    }

    $uid = (int)($_SESSION['uid'] ?? 0);
    if (!$uid) erro('auth', 401);

    // ----- Operações da carteira -----
    if ($acao === 'investir' && $post) {
        $valor = round((float)($in['valor'] ?? 0), 2);
        $ativoId = (int)($in['ativo_id'] ?? 0);
        if ($valor < MIN_INVEST) erro('O investimento mínimo é de 5.000 Kz.');
        $db->beginTransaction();
        $s = $db->prepare("SELECT saldo FROM carteira WHERE utilizador_id=? FOR UPDATE"); $s->execute([$uid]);
        if ($valor > (float)$s->fetchColumn()) { $db->rollBack(); erro('Saldo insuficiente.'); }
        tick($db);
        $s = $db->prepare("SELECT preco FROM ativos WHERE id=?"); $s->execute([$ativoId]);
        $preco = $s->fetchColumn();
        if (!$preco) { $db->rollBack(); erro('Activo inválido.'); }
        $db->prepare("INSERT INTO investimentos (utilizador_id,ativo_id,valor,unidades,preco_compra,criado_em) VALUES (?,?,?,?,?,?)")
           ->execute([$uid, $ativoId, $valor, $valor / $preco, $preco, time()]);
        $db->prepare("UPDATE carteira SET saldo=saldo-? WHERE utilizador_id=?")->execute([$valor, $uid]);
        $db->commit();
    } elseif ($acao === 'resgatar' && $post) {
        $db->beginTransaction();
        tick($db);
        $s = $db->prepare("SELECT i.id, i.unidades, a.preco FROM investimentos i JOIN ativos a ON a.id=i.ativo_id
                           WHERE i.id=? AND i.utilizador_id=? AND i.estado='ativo' FOR UPDATE");
        $s->execute([(int)($in['id'] ?? 0), $uid]);
        $r = $s->fetch();
        if (!$r) { $db->rollBack(); erro('Investimento não encontrado.'); }
        $v = round((float)$r['unidades'] * (float)$r['preco'], 2);
        $db->prepare("UPDATE investimentos SET estado='resgatado', valor_resgate=? WHERE id=?")->execute([$v, $r['id']]);
        $db->prepare("UPDATE carteira SET saldo=saldo+? WHERE utilizador_id=?")->execute([$v, $uid]);
        $db->commit();
    } elseif ($acao === 'reiniciar' && $post) {
        $db->prepare("DELETE FROM investimentos WHERE utilizador_id=?")->execute([$uid]);
        $db->prepare("DELETE FROM patrimonio_hist WHERE utilizador_id=?")->execute([$uid]);
        $db->prepare("UPDATE carteira SET saldo=? WHERE utilizador_id=?")->execute([SALDO_INICIAL, $uid]);
    }
    tick($db);
    echo json_encode(estado($db, $uid));
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    erro('Erro no servidor: '.$e->getMessage(), 500);
}
