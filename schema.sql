CREATE DATABASE IF NOT EXISTS carteira CHARACTER SET utf8mb4;
USE carteira;
DROP TABLE IF EXISTS patrimonio_hist, historico, investimentos, ativos, carteira, utilizadores;

CREATE TABLE utilizadores (id INT AUTO_INCREMENT PRIMARY KEY, nome VARCHAR(80) NOT NULL, email VARCHAR(120) NOT NULL UNIQUE, senha VARCHAR(255) NOT NULL);
CREATE TABLE carteira (utilizador_id INT PRIMARY KEY, saldo DECIMAL(18,2) NOT NULL);
CREATE TABLE patrimonio_hist (id BIGINT AUTO_INCREMENT PRIMARY KEY, utilizador_id INT NOT NULL, valor DECIMAL(18,2) NOT NULL, ts INT NOT NULL, INDEX (utilizador_id, ts));
CREATE TABLE ativos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  simbolo VARCHAR(10) NOT NULL, nome VARCHAR(60) NOT NULL, categoria VARCHAR(30) NOT NULL,
  preco DECIMAL(24,8) NOT NULL, preco_inicial DECIMAL(24,8) NOT NULL,
  drift DOUBLE NOT NULL, volatilidade DOUBLE NOT NULL, atualizado_em INT NOT NULL
);
CREATE TABLE historico (
  id BIGINT AUTO_INCREMENT PRIMARY KEY, ativo_id INT NOT NULL,
  preco DECIMAL(24,8) NOT NULL, ts INT NOT NULL, INDEX (ativo_id, ts)
);
CREATE TABLE investimentos (
  id INT AUTO_INCREMENT PRIMARY KEY, utilizador_id INT NOT NULL, ativo_id INT NOT NULL,
  valor DECIMAL(18,2) NOT NULL, unidades DECIMAL(28,10) NOT NULL, preco_compra DECIMAL(24,8) NOT NULL,
  criado_em INT NOT NULL, estado ENUM('ativo','resgatado') DEFAULT 'ativo', valor_resgate DECIMAL(18,2) NULL
);

-- simbolo, nome, categoria, preço (Kz), preço inicial, drift/min, volatilidade/min, ts
INSERT INTO ativos (simbolo,nome,categoria,preco,preco_inicial,drift,volatilidade,atualizado_em) VALUES
('BAI','Banco BAI (Acções)','Acções',12500,12500,0.00004,0.0007,UNIX_TIMESTAMP()),
('UNI','Unitel (Acções)','Acções',8200,8200,0.00003,0.0008,UNIX_TIMESTAMP()),
('OT-NR','Obrigações do Tesouro OT-NR','Títulos do Tesouro',10000,10000,0.00002,0.00003,UNIX_TIMESTAMP()),
('BT','Bilhetes do Tesouro','Títulos do Tesouro',10000,10000,0.000015,0.00002,UNIX_TIMESTAMP()),
('FMM','Fundo Multi Mercado','Fundos',5000,5000,0.00003,0.0002,UNIX_TIMESTAMP()),
('FIL','Fundo Imobiliário Luanda','Imóveis',25000,25000,0.00003,0.0001,UNIX_TIMESTAMP()),
('BFA','Banco BFA (Acções)','Acções',9800,9800,0.00004,0.0007,UNIX_TIMESTAMP()),
('ENSA','ENSA Seguros (Acções)','Acções',6400,6400,0.00003,0.0006,UNIX_TIMESTAMP()),
('OT-ME','Obrigações do Tesouro em Moeda Estrangeira','Títulos do Tesouro',20000,20000,0.00002,0.00004,UNIX_TIMESTAMP()),
('FAA','Fundo Acções Angola','Fundos',7500,7500,0.00004,0.0003,UNIX_TIMESTAMP()),
('FIC','Fundo Imobiliário Comercial','Imóveis',40000,40000,0.00003,0.00012,UNIX_TIMESTAMP()),
('BTC','Bitcoin','Criptomoedas',98000000,98000000,0.00005,0.0030,UNIX_TIMESTAMP()),
('ETH','Ethereum','Criptomoedas',3200000,3200000,0.00005,0.0035,UNIX_TIMESTAMP()),
('DLC','DolphinCoin','Criptomoedas',150,150,0.00008,0.0060,UNIX_TIMESTAMP()),
('RVL','Revolut','Criptomoedas',4500,4500,0.00004,0.0025,UNIX_TIMESTAMP()),
('SOL','Solana','Criptomoedas',180000,180000,0.00005,0.0040,UNIX_TIMESTAMP());
INSERT INTO historico (ativo_id,preco,ts) SELECT id,preco,UNIX_TIMESTAMP() FROM ativos;
