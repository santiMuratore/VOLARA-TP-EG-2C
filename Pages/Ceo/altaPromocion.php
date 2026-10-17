<?php
require_once __DIR__ . '/../Includes/header.php';
if($_SERVER["REQUEST_METHOD"] == "POST"){
    $desc = $_POST["tipo"];
    if($desc == "Descuento") echo "Descuento";
    else echo "2x1";
    
}

?>

<form action="altaPromocion.php" method="post">
    <label for="descripcion" class="promocion">Descripción</label>
    <input type="text" class="promocion" id="descripcion" name="descripcion">
    <br>
    <label for="tipo" class="promocion">Tipo de promocion</label>
    <select name="tipo" class="promocion" id="tipo">
        <option value="Descuento">Descuento</option>
        <option value="2x1">2x1</option>
    </select>
    <br>
    <label for="descuento" class="promocion">Descuento</label>
    <input type="range" class="promocion" id="descuento">
    <br>
    <input type="submit">
</form>
