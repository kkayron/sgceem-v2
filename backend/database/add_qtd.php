<?php
$pdo = new PDO("mysql:host=127.0.0.1;dbname=sgceem_v2", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
try { $pdo->exec("ALTER TABLE fin_empenhos ADD COLUMN qtd_consumida DECIMAL(15,2) NOT NULL DEFAULT 0.00"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE fin_empenhos ADD COLUMN qtd_retida DECIMAL(15,2) NOT NULL DEFAULT 0.00"); } catch (Exception $e) {}
echo "Colunas adicionadas!";
