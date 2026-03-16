<?php
include 'conexao.php';
header('Content-Type: application/json');

$query = "SELECT id, nome FROM fin_pregao ORDER BY nome ASC";
$result = mysqli_query($conn, $query);
$pregoes = [];

while($row = mysqli_fetch_assoc($result)){
  $pregoes[] = $row;
}

echo json_encode($pregoes);
?>