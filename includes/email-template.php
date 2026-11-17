<?php
/* Shared NextTrip email design. Matches the OTP email style. */
require_once __DIR__ . '/booking-lib.php';
require_once __DIR__ . '/mailer.php';

// Set this to your live site address, so links in emails open the right page.
const EMAIL_SITE_URL = 'https://nexttrip.byethost7.com';

function ne($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function email_money($n) { return '₹' . number_format((float) $n, 2); }

/* Outer card (same look as the OTP email) */
function email_layout($label, $title, $bodyHtml, $cta = null) {
    $btn = '';
    if ($cta) {
        $btn = '<div style="text-align:center;margin:26px 0 6px;"><a href="' . ne($cta[0]) . '" style="display:inline-block;background:#1f8a54;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;padding:13px 26px;border-radius:12px;">' . ne($cta[1]) . '</a></div>';
    }
    return '<div style="background:#f3f6f4;padding:24px 12px;">
  <div style="font-family:Arial,Helvetica,sans-serif;max-width:620px;margin:0 auto;padding:32px 28px;background:#fdfbf6;border-radius:16px;">
    <p style="color:#556155;font-size:12px;letter-spacing:.06em;text-transform:uppercase;margin:0 0 8px;">' . ne($label) . '</p>
    <h2 style="color:#12301d;margin:0 0 16px;font-size:22px;">' . ne($title) . '</h2>
    ' . $bodyHtml . '
    ' . $btn . '
    <hr style="border:0;border-top:1px solid #e6ebe7;margin:26px 0 14px;">
    <p style="color:#8b988a;font-size:12px;line-height:1.6;margin:0;">NextTrip · Smart tourism platform · India<br>This is an automated message. Please do not reply to this email.</p>
  </div></div>';
}

/* One label/value row */
function email_row($k, $vHtml) {
    return '<tr><td style="padding:9px 0;color:#6b7a6f;font-size:13px;width:38%;border-bottom:1px solid #eef2ef;vertical-align:top;">' . ne($k) . '</td>'
         . '<td style="padding:9px 0;color:#1d2a24;font-size:14px;font-weight:600;border-bottom:1px solid #eef2ef;">' . $vHtml . '</td></tr>';
}

/* Calculation row */
function email_calc($label, $amount, $bold = false, $green = false) {
    $style = 'padding:7px 0;font-size:14px;color:' . ($green ? '#146339' : '#33443b') . ';' . ($bold ? 'font-weight:700;' : '');
    return '<tr><td style="' . $style . '">' . ne($label) . '</td><td style="' . $style . 'text-align:right;">' . ne($amount) . '</td></tr>';
}

/* Loads everything an email needs about one booking */
function email_booking_data($bid) {
    $b = bk_row("SELECT b.*, p.package_name, p.price_per_person, p.duration_days,
                        ag.full_name AS agent_name, ag.city AS agent_city,
                        u.first_name, u.last_name, u.phone AS user_phone, ua.email AS user_email,
                        (SELECT a2.email FROM agents ag2 JOIN accounts a2 ON a2.account_id = ag2.account_id WHERE ag2.agent_id = b.agent_id) AS agent_email,
                        (SELECT pm.razorpay_payment_id FROM payments pm WHERE pm.booking_id = b.booking_id AND pm.payment_status = 'Success' LIMIT 1) AS pay_id,
                        (SELECT pm.paid_at FROM payments pm WHERE pm.booking_id = b.booking_id AND pm.payment_status = 'Success' LIMIT 1) AS paid_at
                 FROM bookings b
                 JOIN packages p ON p.package_id = b.package_id
                 JOIN agents ag ON ag.agent_id = b.agent_id
                 JOIN users u ON u.user_id = b.user_id
                 JOIN accounts ua ON ua.account_id = u.account_id
                 WHERE b.booking_id = " . (int) $bid);
    if (!$b) return null;
    $pid = (int) $b['package_id'];
    $b['traveller'] = trim($b['first_name'] . ' ' . $b['last_name']);
    $b['places'] = bk_rows("SELECT d.destination_name, d.city, d.state FROM package_destinations pd
                            JOIN destinations d ON d.destination_id = pd.destination_id
                            WHERE pd.package_id = $pid ORDER BY pd.display_order");
    $b['inclusions'] = bk_rows("SELECT item_name FROM package_inclusions WHERE package_id = $pid AND status = 'Active' ORDER BY display_order");
    $b['exclusions'] = bk_rows("SELECT item_name FROM package_exclusions WHERE package_id = $pid AND status = 'Active' ORDER BY display_order");
    return $b;
}

/* Booking details: package, places, agent, travel dates, guests, calculation, payment ID */
function email_booking_block($d) {
    $price = (float) $d['package_price'];
    $a = (int) $d['adult_count'];
    $c = (int) $d['child_count'];
    $end = date('d M Y', strtotime($d['travel_date'] . ' +' . (int) $d['duration_days'] . ' days'));

    $places = $d['places'] ? implode('<br>', array_map(function ($p) {
        return '• ' . ne($p['destination_name']) . ' <span style="color:#8b988a">(' . ne($p['city']) . ', ' . ne($p['state']) . ')</span>';
    }, $d['places'])) : '—';

    $html = '<table style="width:100%;border-collapse:collapse;margin:18px 0 8px;">'
        . email_row('Booking ID', '#' . (int) $d['booking_id'])
        . email_row('Package', ne($d['package_name']))
        . email_row('Places covered', $places)
        . email_row('Travel dates', ne(date('d M Y', strtotime($d['travel_date']))) . ' – ' . ne($end))
        . email_row('Travellers', $a . ' adult' . ($a === 1 ? '' : 's') . ($c ? ', ' . $c . ' child' . ($c === 1 ? '' : 'ren') : ''))
        . email_row('Travel partner', ne($d['agent_name']) . (!empty($d['agent_city']) ? ' · ' . ne($d['agent_city']) : ''))
        . '</table>';

    $calc = '<p style="font-size:13px;font-weight:700;color:#17452a;margin:18px 0 6px;text-transform:uppercase;letter-spacing:.05em;">Payment summary</p>'
        . '<table style="width:100%;border-collapse:collapse;border-top:1px solid #e6ebe7;">'
        . email_calc($a . ' adult' . ($a === 1 ? '' : 's') . ' × ' . email_money($price), email_money($price * $a))
        . ($c > 0 ? email_calc($c . ' child' . ($c === 1 ? '' : 'ren') . ' × ' . email_money($price * 0.5) . ' (50%)', email_money($price * 0.5 * $c)) : '')
        . ((float) $d['discount_amount'] > 0 ? email_calc('Coupon ' . ($d['coupon_code'] ?: '') . ' discount', '− ' . email_money($d['discount_amount']), false, true) : '')
        . email_calc('Subtotal', email_money($d['subtotal']), true)
        . email_calc('GST (5%)', email_money($d['gst_amount']))
        . email_calc('Total ' . ($d['pay_id'] ? 'paid' : 'payable'), email_money($d['total_amount']), true, true)
        . '</table>';

    $pay = $d['pay_id'] ? '<table style="width:100%;border-collapse:collapse;margin-top:14px;">'
        . email_row('Payment ID', '<code style="font-size:13px;">' . ne($d['pay_id']) . '</code>')
        . email_row('Paid on', ne(date('d M Y, g:i A', strtotime($d['paid_at'] ?: 'now'))))
        . '</table>' : '';

    return $html . $calc . $pay;
}

/* Included and excluded lists, side by side */
function email_includes_block($d) {
    $list = function ($rows, $empty) {
        if (!$rows) return '<li style="color:#8b988a;">' . ne($empty) . '</li>';
        return implode('', array_map(function ($r) { return '<li style="margin:0 0 6px;color:#33443b;">' . ne($r['item_name']) . '</li>'; }, $rows));
    };
    return '<table style="width:100%;border-collapse:separate;border-spacing:0;margin:20px 0 4px;"><tr>'
        . '<td style="vertical-align:top;width:50%;padding-right:12px;"><p style="margin:0 0 8px;color:#17452a;font-weight:700;font-size:13px;">Included</p>'
        . '<ul style="margin:0;padding-left:18px;font-size:13.5px;line-height:1.5;">' . $list($d['inclusions'], 'Not listed') . '</ul></td>'
        . '<td style="vertical-align:top;width:50%;padding-left:12px;"><p style="margin:0 0 8px;color:#a33c3c;font-weight:700;font-size:13px;">Not included</p>'
        . '<ul style="margin:0;padding-left:18px;font-size:13.5px;line-height:1.5;">' . $list($d['exclusions'], 'Not listed') . '</ul></td>'
        . '</tr></table>';
}

/* Standard booking email: intro text, details, inclusions, optional button */
function email_send_booking($to, $name, $subject, $label, $title, $intro, $d, $cta = null, $attachments = [], $withIncl = true) {
    $body = '<p style="color:#33443b;font-size:14.5px;line-height:1.7;margin:0 0 6px;">Hi ' . ne($name) . ',</p>'
          . '<p style="color:#33443b;font-size:14.5px;line-height:1.7;margin:0 0 6px;">' . ne($intro) . '</p>'
          . email_booking_block($d)
          . ($withIncl ? email_includes_block($d) : '');
    $html = email_layout($label, $title, $body, $cta);
    $text = "Hi $name,\n\n$intro\n\nBooking #{$d['booking_id']} - {$d['package_name']}\nTotal: " . email_money($d['total_amount']) . "\n\n— NextTrip Team";
    return send_mail($to, $name, $subject, $html, $text, $attachments);
}

/* Receipt sent as an attachment (opens in any browser, print to PDF) */
function email_receipt_html($d) {
    $sig = '';
    foreach (['png', 'PNG', 'jpg', 'JPG', 'jpeg', 'webp', 'gif'] as $ext) {
        $f = __DIR__ . '/../assets/images/signature.' . $ext;
        if (is_file($f)) { $sig = 'data:image/' . strtolower($ext === 'jpg' ? 'jpeg' : $ext) . ';base64,' . base64_encode(file_get_contents($f)); break; }
    }
    $no = 'NT-' . str_pad((string) $d['booking_id'], 6, '0', STR_PAD_LEFT);
    $body = '<h1 style="font-family:Georgia,serif;color:#17452a;margin:0;">NextTrip</h1>'
          . '<p style="color:#6b7a6f;margin:4px 0 18px;">Booking receipt · ' . ne($no) . '</p>'
          . '<p style="color:#33443b;font-size:14px;">Traveller: <b>' . ne($d['traveller']) . '</b> · ' . ne($d['user_email']) . ($d['user_phone'] ? ' · ' . ne($d['user_phone']) : '') . '</p>'
          . email_booking_block($d)
          . '<div style="margin-top:34px;text-align:center;">' . ($sig ? '<img src="' . $sig . '" alt="Signature" style="height:70px;">' : '')
          . '<div style="border-top:1.5px solid #1d2a24;width:240px;margin:6px auto 0;"></div><p style="font-size:12px;color:#6b7a6f;margin:6px 0 0;">Authorised signature</p></div>';
    return '<!doctype html><html><head><meta charset="utf-8"><title>Receipt ' . ne($no) . '</title></head>'
         . '<body style="margin:0;background:#ffffff;"><div style="font-family:Arial,Helvetica,sans-serif;max-width:720px;margin:0 auto;padding:28px;">'
         . $body . '</div></body></html>';
}

/* ---------- PDF receipt (built without any library) ---------- */
function money_pdf($n) { return 'Rs. ' . number_format((float) $n, 2); }

function pdf_str($text) {
    $text = str_replace('₹', 'Rs. ', (string) $text);
    $conv = @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $text);
    if ($conv === false) $conv = preg_replace('/[^\x20-\x7E]/', '?', $text);
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $conv);
}

function pdf_w($text, $size, $bold = false) {
    $plain = preg_replace('/[^\x20-\x7E]/', '?', str_replace('₹', 'Rs. ', (string) $text));
    return strlen($plain) * $size * ($bold ? 0.55 : 0.5);
}

function pdf_build(array $objs) {
    $out = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objs as $num => $body) {
        $offsets[$num] = strlen($out);
        $out .= "$num 0 obj\n$body\nendobj\n";
    }
    $count = count($objs) + 1;
    $xref = strlen($out);
    $out .= "xref\n0 $count\n0000000000 65535 f \n";
    for ($i = 1; $i < $count; $i++) $out .= sprintf("%010d 00000 n \n", $offsets[$i]);
    $out .= "trailer\n<< /Size $count /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
    return $out;
}

function email_receipt_pdf($d) {
    /* Signature image: converted to JPEG with GD, so it can be embedded in the PDF */
    $jpg = ''; $imgW = 0; $imgH = 0;
    foreach (['png', 'PNG', 'jpg', 'JPG', 'jpeg', 'webp', 'gif'] as $ext) {
        $f = __DIR__ . '/../assets/images/signature.' . $ext;
        if (!is_file($f) || !function_exists('imagecreatefromstring')) continue;
        $src = @imagecreatefromstring(file_get_contents($f));
        if (!$src) continue;
        $imgW = imagesx($src); $imgH = imagesy($src);
        $canvas = imagecreatetruecolor($imgW, $imgH);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopy($canvas, $src, 0, 0, 0, 0, $imgW, $imgH);
        ob_start(); imagejpeg($canvas, null, 90); $jpg = ob_get_clean();
        imagedestroy($src); imagedestroy($canvas);
        break;
    }

    $ops = [];
    $txt = function ($x, $y, $size, $bold, $text) use (&$ops) {
        $ops[] = 'BT /' . ($bold ? 'F2' : 'F1') . ' ' . $size . ' Tf ' . round($x, 1) . ' ' . round($y, 1) . ' Td (' . pdf_str($text) . ') Tj ET';
    };
    $color = function ($rgb) use (&$ops) { $ops[] = $rgb . ' rg'; };
    $rule = function ($y) use (&$ops) { $ops[] = '0.88 0.91 0.89 RG 0.6 w 40 ' . $y . ' m 555 ' . $y . ' l S'; };
    $section = function ($y, $title) use ($txt, $color, $rule) {
        $color('0.07 0.39 0.22');
        $txt(40, $y, 10, true, strtoupper($title));
        $rule($y - 6);
        return $y - 22;
    };
    $row = function ($y, $label, $value) use ($txt, $color) {
        $color('0.42 0.48 0.44');
        $txt(40, $y, 9.5, false, $label);
        $color('0.11 0.16 0.14');
        $lines = explode("\n", wordwrap((string) $value, 80, "\n", true));
        foreach ($lines as $i => $ln) $txt(200, $y - $i * 13, 10.5, false, $ln);
        return $y - max(1, count($lines)) * 13 - 6;
    };
    $amount = function ($y, $label, $amt, $bold = false, $green = false) use ($txt, $color) {
        $color($green ? '0.08 0.38 0.22' : '0.2 0.25 0.22');
        $txt(40, $y, 10.5, $bold, $label);
        $txt(555 - pdf_w($amt, 10.5, $bold), $y, 10.5, $bold, $amt);
        return $y - 18;
    };

    /* Header band */
    $ops[] = '0.07 0.39 0.22 rg 0 782 595 60 re f';
    $color('1 1 1');
    $txt(40, 810, 20, true, 'NextTrip');
    $txt(40, 794, 10, false, 'Booking receipt');
    $no = 'NT-' . str_pad((string) $d['booking_id'], 6, '0', STR_PAD_LEFT);
    $txt(555 - pdf_w($no, 14, true), 806, 14, true, $no);
    $txt(555 - pdf_w('Confirmed and paid', 10), 790, 10, false, 'Confirmed and paid');

    $a = (int) $d['adult_count']; $c = (int) $d['child_count'];
    $price = (float) $d['package_price'];
    $end = date('d M Y', strtotime($d['travel_date'] . ' +' . (int) $d['duration_days'] . ' days'));
    $places = $d['places'] ? implode(', ', array_map(function ($p) { return $p['destination_name']; }, $d['places'])) : '-';

    $y = $section(756, 'Traveller');
    $y = $row($y, 'Name', $d['traveller']);
    $y = $row($y, 'Email', $d['user_email']);
    $y = $row($y, 'Phone', $d['user_phone'] ?: '-');
    $y = $row($y, 'Total persons', ($a + $c) . ' (' . $a . ' adult' . ($a === 1 ? '' : 's') . ($c ? ', ' . $c . ' child' . ($c === 1 ? '' : 'ren') : '') . ')');
    $y -= 8;

    $y = $section($y, 'Booking details');
    $y = $row($y, 'Package', $d['package_name']);
    $y = $row($y, 'Places covered', $places);
    $y = $row($y, 'Travel dates', date('d M Y', strtotime($d['travel_date'])) . ' - ' . $end);
    $y = $row($y, 'Travel partner', $d['agent_name'] . (!empty($d['agent_city']) ? ', ' . $d['agent_city'] : ''));
    $y = $row($y, 'Booking ID', '#' . (int) $d['booking_id']);
    $y -= 8;

    $y = $section($y, 'Payment summary');
    $y = $amount($y, $a . ' adult' . ($a === 1 ? '' : 's') . ' x ' . money_pdf($price), money_pdf($price * $a));
    if ($c > 0) $y = $amount($y, $c . ' child' . ($c === 1 ? '' : 'ren') . ' x ' . money_pdf($price * 0.5) . ' (50%)', money_pdf($price * 0.5 * $c));
    if ((float) $d['discount_amount'] > 0) {
        $y = $amount($y, 'Coupon ' . ($d['coupon_code'] ?: '') . ' discount', '- ' . money_pdf($d['discount_amount']), false, true);
    }
    $y = $amount($y, 'Subtotal', money_pdf($d['subtotal']), true);
    $y = $amount($y, 'GST (5%)', money_pdf($d['gst_amount']));
    $y = $amount($y, 'Total ' . ($d['pay_id'] ? 'paid' : 'payable'), money_pdf($d['total_amount']), true, true);
    if ($d['pay_id']) {
        $y = $row($y - 4, 'Payment ID', $d['pay_id']);
        $y = $row($y, 'Paid on', $d['paid_at'] ? date('d M Y, g:i A', strtotime($d['paid_at'])) : '-');
    }
    $y -= 8;

    /* Inclusions and exclusions */
    $y = $section($y, 'Inclusions');
    $top = $y;
    $color('0.07 0.39 0.22'); $txt(40, $top, 10, true, 'Included');
    $color('0.64 0.23 0.23'); $txt(310, $top, 10, true, 'Not included');
    $yl = $top - 16;
    foreach (array_slice($d['inclusions'], 0, 8) as $it) {
        $color('0.2 0.25 0.22'); $txt(40, $yl, 9.5, false, '- ' . substr($it['item_name'], 0, 50)); $yl -= 13;
    }
    if (!$d['inclusions']) { $color('0.55 0.6 0.56'); $txt(40, $yl, 9.5, false, 'Not listed'); $yl -= 13; }
    $yr = $top - 16;
    foreach (array_slice($d['exclusions'], 0, 8) as $it) {
        $color('0.2 0.25 0.22'); $txt(310, $yr, 9.5, false, '- ' . substr($it['item_name'], 0, 50)); $yr -= 13;
    }
    if (!$d['exclusions']) { $color('0.55 0.6 0.56'); $txt(310, $yr, 9.5, false, 'Not listed'); $yr -= 13; }

    /* Signature and footer */
    $sigY = 92;
    if ($jpg) {
        $h = 60;
        $w = min(180, $h * $imgW / $imgH);
        $ops[] = 'q ' . round($w, 1) . ' 0 0 ' . $h . ' 400 ' . ($sigY + 4) . ' cm /Im1 Do Q';
    }
    $ops[] = '0.11 0.16 0.14 RG 0.8 w 380 ' . $sigY . ' m 555 ' . $sigY . ' l S';
    $color('0.42 0.48 0.44');
    $txt(400, $sigY - 14, 9, false, 'Authorised signature');
    $txt(40, 40, 8.5, false, 'Computer-generated receipt. Thank you for travelling with NextTrip.');

    /* Assemble the PDF objects */
    $content = implode("\n", $ops);
    $xobj = $jpg ? '/XObject << /Im1 7 0 R >>' : '';
    $objs = [];
    $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objs[2] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
    $objs[3] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R /F2 6 0 R >> ' . $xobj . ' >> /Contents 4 0 R >>';
    $objs[4] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
    $objs[5] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
    $objs[6] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
    if ($jpg) {
        $objs[7] = '<< /Type /XObject /Subtype /Image /Width ' . $imgW . ' /Height ' . $imgH
                 . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($jpg)
                 . " >>\nstream\n" . $jpg . "\nendstream";
    }
    return pdf_build($objs);
}