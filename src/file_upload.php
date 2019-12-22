<?php

const MAX_DESCRIPTION_LENGTH = 255;

const TWO_MEGS = 2 * 1024 * 1024;

const SUCCESSFUL_EXECUTION_RESULT = 0;

function validateDescription(): array
{
    $errors = [];
    if (!isset($_POST['description'])) {
        return ['Description is required'];
    }

    if (strlen($_POST['description']) > MAX_DESCRIPTION_LENGTH) {
        $errors[] = "Description is too long";
    }

    if ($_POST['description'] !== htmlspecialchars($_POST['description'])) {
        $errors[] = "Description may not contain HTML syntax";
    }

    return $errors;
}

$descriptionErrors = validateDescription();
if (!empty($descriptionErrors)) {
    $message = urlencode(implode('<br/>', $descriptionErrors));
    header('Location: upload.php?error=' . $message);
    exit;
}

/**
 * return an array of errors relating to the uploaded file
 */
function validateFile(): array
{
    $errors = [];

    // make sure there were not http errors
    if ($_FILES['upload']['error'] !== 0) {
        $errors = ['There was an error uploading the file'];
    }

    // verify the file type
    $tempName = mime_content_type($_FILES['upload']['tmp_name']);
    $allowedMimeTypes = ['image/gif', 'image/jpeg', 'image/jpg', 'image/png'];
    if (!in_array($tempName, $allowedMimeTypes)) {
        $errors = ['You may only upload gif, jpeg, or png files'];
    }

    //Check that the file is not too big
    if ($_FILES['upload']["size"] > TWO_MEGS) {
        $errors = ["File must be smaller than 2MB"];
    }

    // scan for viruses
    $safePath = $_FILES['upload']['tmp_name'];
    $command = escapeshellcmd('clamscan ' . $safePath);
    $out = '';
    $int = -1;
    exec($command, $out, $int);

    if ($int !== SUCCESSFUL_EXECUTION_RESULT) {
        $errors = ['File not allowed'];
    }

    return $errors;
}

/**
 * Move the uploaded file to a permanent location
 */
function manageFileUpload(): array
{
    // make certain that the file didn't already exist somewhere on our system
    if (false === is_uploaded_file($_FILES['upload']['tmp_name'])) {
        throw new Exception('Server error');
    }
    
    // choose an unpredictable name and make sure we have the right extension
    $mimeType = mime_content_type($_FILES['upload']['tmp_name']);
    $mimeMapping = [
        'image/gif' => 'gif',
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpf',
        'image/png' => 'png'
    ];
    $extension = $mimeMapping[$mimeType];
    $randomName = bin2hex(random_bytes(8)) . '.' . $extension;

    // make sure upload path exists
    $uploadPath = __DIR__ . '/../upload/';
    if (false === is_dir($uploadPath)) {
        if (false === mkdir($uploadPath)) {
            throw new Exception('Server error');
        }
    }

    // try move the uploaded file
    if (move_uploaded_file($_FILES['upload']['tmp_name'], $uploadPath . $randomName)) {
        $file = [
            'name' => $randomName,
            'path' => $uploadPath
        ];
        return $file;
    } else {
        throw new Exception('Server error');
    }
}

if (!empty($_FILES)) {
    $errors = validateFile();
    if (!empty($errors)) {
        $message = urlencode(implode('<br/>', $errors));
        header('Location: upload.php?error=' . $message);
        exit;
    }

    try {
        manageFileUpload();
    } catch (Exception $e) {
        $message = urlencode($e->getMessage());
        header('Location: upload.php?error=' . $message);
        exit;
    }
} else {
    $message = urlencode('You have to upload a file');
    header('Location: upload.php?error=' . $message);
    exit;
}

echo "File uploaded";
