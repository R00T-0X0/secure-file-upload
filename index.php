
<?php

define("MAX_FILE_SIZE", 5 * 1024 * 1024);

define("UPLOAD_DIRECTORY", __DIR__ . "/uploads/");

define("ALLOWED_FILES", [
    "jpg" => "image/jpeg",
    "jpeg" => "image/jpeg",
    "png" => "image/png",
    "pdf" => "application/pdf"
]);


function create_upload_directory()
{
    if (!is_dir(UPLOAD_DIRECTORY)) {
        mkdir(UPLOAD_DIRECTORY, 0700, true);
    }

    if (!is_dir(UPLOAD_DIRECTORY) || !is_writable(UPLOAD_DIRECTORY)) {
        throw new RuntimeException("Upload directory is not available.");
    }
}


function check_upload_error($error_code)
{
    if ($error_code === UPLOAD_ERR_OK) {
        return;
    }

    $errors = [
        UPLOAD_ERR_INI_SIZE => "The file exceeds the server upload limit.",
        UPLOAD_ERR_FORM_SIZE => "The file exceeds the allowed size.",
        UPLOAD_ERR_PARTIAL => "The file was partially uploaded.",
        UPLOAD_ERR_NO_FILE => "Please select a file.",
        UPLOAD_ERR_NO_TMP_DIR => "Temporary directory is missing.",
        UPLOAD_ERR_CANT_WRITE => "Failed to write the uploaded file.",
        UPLOAD_ERR_EXTENSION => "A PHP extension stopped the upload."
    ];

    $message = $errors[$error_code] ?? "Unknown upload error.";

    throw new RuntimeException($message);
}


function validate_uploaded_file($file)
{
    check_upload_error($file["error"]);

    if (!is_uploaded_file($file["tmp_name"])) {
        throw new RuntimeException("Invalid uploaded file.");
    }
}


function validate_file_size($temporary_path)
{
    $file_size = filesize($temporary_path);

    if ($file_size === false || $file_size === 0) {
        throw new RuntimeException("The uploaded file is empty or invalid.");
    }

    if ($file_size > MAX_FILE_SIZE) {
        throw new RuntimeException("Maximum file size is 5 MB.");
    }
}


function get_file_extension($filename)
{
    return strtolower(
        pathinfo($filename, PATHINFO_EXTENSION)
    );
}


function get_file_mime_type($temporary_path)
{
    $file_info = finfo_open(FILEINFO_MIME_TYPE);

    if ($file_info === false) {
        throw new RuntimeException("Unable to inspect file content.");
    }

    $mime_type = finfo_file($file_info, $temporary_path);

    finfo_close($file_info);

    if ($mime_type === false) {
        throw new RuntimeException("Unable to detect file type.");
    }

    return $mime_type;
}


function validate_file_type($extension, $mime_type)
{
    if (!array_key_exists($extension, ALLOWED_FILES)) {
        throw new RuntimeException("File extension is not allowed.");
    }

    if (ALLOWED_FILES[$extension] !== $mime_type) {
        throw new RuntimeException("File content does not match its extension.");
    }
}


function generate_random_filename($extension)
{
    return bin2hex(random_bytes(16)) . "." . $extension;
}


function move_uploaded_file_to_storage($temporary_path, $new_filename)
{
    $destination = UPLOAD_DIRECTORY . $new_filename;

    if (!move_uploaded_file($temporary_path, $destination)) {
        throw new RuntimeException("Failed to save the uploaded file.");
    }

    chmod($destination, 0600);

    return $new_filename;
}


function process_uploaded_file($file)
{
    validate_uploaded_file($file);

    validate_file_size($file["tmp_name"]);

    $extension = get_file_extension($file["name"]);

    $mime_type = get_file_mime_type($file["tmp_name"]);

    validate_file_type($extension, $mime_type);

    $new_filename = generate_random_filename($extension);

    return move_uploaded_file_to_storage(
        $file["tmp_name"],
        $new_filename
    );
}


function handle_upload()
{
    if (!isset($_FILES["uploaded_file"])) {
        return null;
    }

    try {
        create_upload_directory();

        $filename = process_uploaded_file($_FILES["uploaded_file"]);

        return [
            "success" => true,
            "message" => "File uploaded successfully.",
            "filename" => $filename
        ];

    } catch (Throwable $error) {
        error_log($error->getMessage());

        return [
            "success" => false,
            "message" => $error->getMessage()
        ];
    }
}


function escape_output($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );
}


$result = handle_upload();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Secure File Upload</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f4f4;
            padding: 30px;
        }

        .container {
            max-width: 550px;
            margin: 50px auto;
            padding: 25px;
            background: white;
            border-radius: 8px;
        }

        h1 {
            text-align: center;
        }

        form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        input, button {
            padding: 12px;
        }

        button {
            background: #222;
            color: white;
            border: none;
            cursor: pointer;
        }

        .success {
            color: green;
            margin-top: 20px;
        }

        .error {
            color: red;
            margin-top: 20px;
        }

        .info {
            background: #eee;
            padding: 12px;
            margin-top: 20px;
        }
    </style>
</head>

<body>

    <div class="container">

        <h1>Secure File Upload</h1>

        <form method="POST" enctype="multipart/form-data">

            <label for="uploaded_file">Choose a file:</label>

            <input
                type="file"
                name="uploaded_file"
                id="uploaded_file"
                accept=".jpg,.jpeg,.png,.pdf"
                required
            >

            <button type="submit">Upload File</button>

        </form>

        <div class="info">
            <strong>Allowed files:</strong> JPG, JPEG, PNG, PDF
            <br>
            <strong>Maximum size:</strong> 5 MB
        </div>

        <?php if ($result !== null): ?>

            <?php if ($result["success"]): ?>

                <div class="success">
                    <p><?php echo escape_output($result["message"]); ?></p>

                    <p>
                        Saved filename:
                        <?php echo escape_output($result["filename"]); ?>
                    </p>
                </div>

            <?php else: ?>

                <div class="error">
                    <?php echo escape_output($result["message"]); ?>
                </div>

            <?php endif; ?>

        <?php endif; ?>

    </div>

</body>
</html>
