<?php
// Get any error messages
$error = getError();
?>
<!DOCTYPE html>
<html class="no-js">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title><?php echo isset($pageTitle) ? h($pageTitle) : 'BlueBlips Mobile'; ?></title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
        <link rel="stylesheet" href="css/styles.css">
    </head>
    <body>
        <div class="header">
            <a href="index.php">
                <img src="img/logo.gif" alt="BlueBlips Logo">
            </a>
        </div>
        
        <?php if (!empty($error)): ?>
        <div style="color: red; padding: 5px;">
            <?php echo h($error); ?>
        </div>
        <?php endif; ?> 