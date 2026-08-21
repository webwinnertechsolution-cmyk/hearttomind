<!DOCTYPE html>
<html>
<head>
  <title>{{ $title }}</title>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0; padding:0; background:#f4f4f4; font-family:Arial, sans-serif;">

  <table width="100%" cellpadding="0" cellspacing="0">
    <tr>
      <td align="center" style="padding: 30px 15px;">

        <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:10px; overflow:hidden;">

          <!-- LOGO -->
          <tr>
            <td align="center" style="background:#1a1a2e; padding:28px 20px;">
              <img src="https://limegreen-shark-270376.hostingersite.com/storage/images/webs/obmP0bzcRDb92PDmSXxw5ktc8Eq1ia5IHiLjnPG1.png"
                   alt="Logo" height="52" style="display:block;">
            </td>
          </tr>

          <!-- HEADING + TEXT -->
          <tr>
            <td style="padding: 36px 40px 40px;">
              <h2 style="margin:0 0 16px; font-size:22px; font-weight:600; color:#1a1a2e;">
                {{ $title }}
              </h2>
              <p style="margin:0; font-size:15px; color:#555555; line-height:1.7;">
                {{ $messageText }}
              </p>
            </td>
          </tr>

        </table>

      </td>
    </tr>
  </table>

</body>
</html>