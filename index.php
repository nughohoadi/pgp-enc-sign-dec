<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PGP Encryption & Decryption with Signing</title>
</head>
<body>
    <h2>Encrypt and Sign XML File using PGP Public/Private Key</h2>
    
    <form action="index.php" method="post" enctype="multipart/form-data">
        <label for="xmlFile">Choose XML file:</label>
        <input type="file" name="xmlFile" id="xmlFile" required><br><br>
        
        <label for="publicKey">Choose PGP Public Key (for encryption):</label>
        <input type="file" name="publicKey" id="publicKey" required><br><br>
        
        <label for="privateKeySign">Choose PGP Private Key (for signing):</label>
        <input type="file" name="privateKeySign" id="privateKeySign" required><br><br>

        <label for="passphraseSign">Enter Passphrase (for signing private key):</label>
        <input type="password" name="passphraseSign" id="passphraseSign"><br><br>
        
        <input type="submit" name="encryptAndSign" value="Encrypt and Sign File">
    </form>

    <hr>

    <h2>Decrypt Encrypted File using PGP Private Key</h2>

    <form action="index.php" method="post" enctype="multipart/form-data">
        <label for="encryptedFile">Choose Encrypted File:</label>
        <input type="file" name="encryptedFile" id="encryptedFile" required><br><br>

        <label for="privateKey">Choose PGP Private Key:</label>
        <input type="file" name="privateKey" id="privateKey" required><br><br>

        <label for="passphrase">Enter Passphrase (optional):</label>
        <input type="password" name="passphrase" id="passphrase"><br><br>

        <input type="submit" name="decrypt" value="Decrypt File">
    </form>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['encryptAndSign']) && isset($_FILES['xmlFile']) && isset($_FILES['publicKey']) && isset($_FILES['privateKeySign'])) {
        // Encryption and signing process
        $xmlFilePath = $_FILES['xmlFile']['tmp_name'];
        $publicKeyPath = $_FILES['publicKey']['tmp_name'];
        $privateKeySignPath = $_FILES['privateKeySign']['tmp_name'];
        $passphraseSign = isset($_POST['passphraseSign']) ? $_POST['passphraseSign'] : '';

        // Read the public and private keys
        $publicKey = file_get_contents($publicKeyPath);
        $privateKeySign = file_get_contents($privateKeySignPath);

        // Create temporary files for the keys
        $tempPublicKeyFile = tempnam(sys_get_temp_dir(), 'pgp_public_key');
        file_put_contents($tempPublicKeyFile, $publicKey);

        $tempPrivateKeySignFile = tempnam(sys_get_temp_dir(), 'pgp_private_key_sign');
        file_put_contents($tempPrivateKeySignFile, $privateKeySign);

        // Create a temporary directory for GPG home
        $gpgHomeDir = sys_get_temp_dir() . '/gnupg';
        if (!is_dir($gpgHomeDir)) {
            mkdir($gpgHomeDir, 0700, true);
        }

        $encryptedFilePath = tempnam(sys_get_temp_dir(), 'encrypted_') . ".gpg";

        // Import private key for signing
        $command = "gpg --batch --yes --no-tty --homedir " . escapeshellarg($gpgHomeDir) . " --import " . escapeshellarg($tempPrivateKeySignFile);
        exec($command, $output, $result);

        if ($result === 0) {
            // Encrypt and sign the file
            $signCommand = "gpg --batch --yes --no-tty --homedir " . escapeshellarg($gpgHomeDir) . " --recipient-file " . escapeshellarg($tempPublicKeyFile) . " --sign --armor --output " . escapeshellarg($encryptedFilePath) . " " . escapeshellarg($xmlFilePath);

            if ($passphraseSign !== '') {
                $signCommand = "gpg --batch --yes --no-tty --homedir " . escapeshellarg($gpgHomeDir) . " --passphrase " . escapeshellarg($passphraseSign) . " --recipient-file " . escapeshellarg($tempPublicKeyFile) . " --sign --armor --output " . escapeshellarg($encryptedFilePath) . " " . escapeshellarg($xmlFilePath);
            }

            exec($signCommand, $output, $result);

            if ($result === 0) {
                echo "<p>File encrypted and signed successfully. <a href='download.php?file=" . urlencode(basename($encryptedFilePath)) . "'>Download Encrypted File</a></p>";
            } else {
                echo "<p>Error encrypting and signing file.</p>";
            }
        } else {
            echo "<p>Error importing private key for signing.</p>";
        }

        // Clean up temporary key files
        unlink($tempPublicKeyFile);
        unlink($tempPrivateKeySignFile);
    } elseif (isset($_POST['decrypt']) && isset($_FILES['encryptedFile']) && isset($_FILES['privateKey'])) {
        // Decryption process
        $encryptedFilePath = $_FILES['encryptedFile']['tmp_name'];
        $privateKeyPath = $_FILES['privateKey']['tmp_name'];
        $passphrase = isset($_POST['passphrase']) ? $_POST['passphrase'] : '';

        $privateKey = file_get_contents($privateKeyPath);

        $tempPrivateKeyFile = tempnam(sys_get_temp_dir(), 'pgp_private_key');
        file_put_contents($tempPrivateKeyFile, $privateKey);

        $gpgHomeDir = sys_get_temp_dir() . '/gnupg';
        if (!is_dir($gpgHomeDir)) {
            mkdir($gpgHomeDir, 0700, true);
        }

        $decryptedFilePath = tempnam(sys_get_temp_dir(), 'decrypted_') . ".xml";

        // Import private key
        $command = "gpg --batch --yes --no-tty --homedir " . escapeshellarg($gpgHomeDir) . " --import " . escapeshellarg($tempPrivateKeyFile);
        exec($command, $output, $result);

        if ($result === 0) {
            // Decrypt the file
            if ($passphrase !== '') {
                $decryptCommand = "gpg --batch --yes --no-tty --homedir " . escapeshellarg($gpgHomeDir) . " --passphrase " . escapeshellarg($passphrase) . " --output " . escapeshellarg($decryptedFilePath) . " --decrypt " . escapeshellarg($encryptedFilePath);
            } else {
                $decryptCommand = "gpg --batch --yes --no-tty --homedir " . escapeshellarg($gpgHomeDir) . " --output " . escapeshellarg($decryptedFilePath) . " --decrypt " . escapeshellarg($encryptedFilePath);
            }
            exec($decryptCommand, $output, $result);

            if ($result === 0) {
                echo "<p>File decrypted successfully. <a href='download.php?file=" . urlencode(basename($decryptedFilePath)) . "'>Download Decrypted File</a></p>";
            } else {
                echo "<p>Error decrypting file.</p>";
            }
        } else {
            echo "<p>Error importing private key for decryption.</p>";
        }

        unlink($tempPrivateKeyFile);
    } else {
        echo "<p>Please provide the necessary files and information.</p>";
    }
}
?>
</body>
</html>
