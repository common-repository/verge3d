<html>
<head>
  <meta charset="UTF-8">
  <style>
    body {
        font-family: Arial, Helvetica, sans-serif;
        color: #333447;
    }
    table, table th, table td {
        border: 1px solid;
        border-collapse: collapse;
        padding: 10px;
    }
  </style>
</head>

<body>
  <p>Here is what the form contains:</p>
  <table class="v3d-form-items-table">
    <tbody>
      <?php foreach ($form_fields as $name => $value): ?>
        <tr>
          <td><?= esc_html($name); ?></td>
          <td><?= is_array($value) ? esc_html(implode(', ', $value)) : esc_html($value); ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</body>
</html>
