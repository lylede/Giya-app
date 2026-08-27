<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>GIYA - Password Reset</title></head>
<body style="margin:0;padding:0;background:#FDF3E3;font-family:Georgia,'Times New Roman',serif">
<table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 16px">
    <tr><td align="center">
        <table width="520" cellpadding="0" cellspacing="0"
               style="background:#fff;border-radius:20px;overflow:hidden;border:1px solid rgba(142,59,47,0.1)">

            <tr>
                <td style="background:#8E3B2F;padding:28px 32px">
                    <p style="color:#D7A94A;font-size:20px;font-weight:700;margin:0">Giya</p>
                    <p style="color:#fff;font-size:20px;font-weight:700;margin:14px 0 4px">Password Reset</p>
                    <p style="color:rgba(255,255,255,0.7);font-size:13px;margin:0;font-family:Arial,sans-serif">
                        Your pilgrimage companion for Metro Cebu
                    </p>
                </td>
            </tr>

            <tr>
                <td style="padding:28px 32px;font-family:Arial,sans-serif">
                    <p style="color:#241C18;font-size:15px;margin:0 0 10px">Hello,</p>
                    <p style="color:#7A6355;font-size:14px;line-height:1.7;margin:0 0 24px">
                        We received a request to reset the password for the GIYA account
                        registered to <strong style="color:#241C18">{{ $email }}</strong>.
                    </p>

                    <table cellpadding="0" cellspacing="0" width="100%">
                        <tr><td align="center" style="padding-bottom:24px">
                            <a href="{{ $resetUrl }}"
                               style="display:inline-block;padding:14px 36px;background:#8E3B2F;color:#fff;
                                      text-decoration:none;border-radius:12px;font-weight:700;font-size:14px">
                                Reset My Password
                            </a>
                        </td></tr>
                    </table>

                    <p style="color:#7A6355;font-size:12px;margin:0 0 6px">
                        If the button does not work, copy this link into your browser:
                    </p>
                    <p style="word-break:break-all;font-size:11px;color:#8E3B2F;margin:0 0 20px">{{ $resetUrl }}</p>

                    <hr style="border:none;border-top:1px solid rgba(142,59,47,0.1);margin:0 0 18px">

                    <p style="color:#B9A493;font-size:12px;line-height:1.6;margin:0">
                        This link expires in <strong>60 minutes</strong>.
                        If you did not request a password reset you can safely ignore this email -
                        your password will not change.
                    </p>
                </td>
            </tr>

            <tr>
                <td style="background:#FDF3E3;padding:16px 32px;text-align:center;border-top:1px solid rgba(142,59,47,0.08)">
                    <p style="color:#B9A493;font-size:11px;margin:0;font-family:Arial,sans-serif">
                        GIYA - Localized Pilgrimage Companion for Metro Cebu
                    </p>
                </td>
            </tr>
        </table>
    </td></tr>
</table>
</body>
</html>
