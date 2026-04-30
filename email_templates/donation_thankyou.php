<?php
/**
 * Donation Thank You Email Template
 */

function getDonationThankYouTemplate($donation) {
    $monastery_name = MONASTERY_NAME;
    $monastery_website = MONASTERY_WEBSITE;
    
    $donor_name = htmlspecialchars($donation['donor_name']);
    $amount = number_format($donation['amount'], 2);
    $category = htmlspecialchars($donation['category_name']);
    $date = date('F d, Y', strtotime($donation['created_at']));
    $receipt_no = 'DON-' . str_pad($donation['donation_id'], 6, '0', STR_PAD_LEFT);
    $payment_method = strtoupper(str_replace('_', ' ', $donation['method'] ?? $donation['payment_method'] ?? 'Bank Transfer'));
    
    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thank You for Your Donation</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #f57c00 0%, #ff9800 100%);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 10px 10px 0 0;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .lotus {
            font-size: 40px;
            margin-bottom: 10px;
        }
        .content {
            background: #fff;
            padding: 30px;
            border: 2px solid #f57c00;
            border-top: none;
        }
        .amount-box {
            background: linear-gradient(135deg, #f57c00 0%, #ff9800 100%);
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 10px;
            margin: 20px 0;
        }
        .amount-box h2 {
            margin: 0;
            font-size: 32px;
        }
        .details {
            background: #fff3e0;
            padding: 15px;
            border-left: 4px solid #f57c00;
            margin: 20px 0;
        }
        .details table {
            width: 100%;
        }
        .details td {
            padding: 5px;
        }
        .details td:first-child {
            font-weight: bold;
            width: 40%;
        }
        .blessing {
            text-align: center;
            font-style: italic;
            color: #f57c00;
            margin: 20px 0;
            font-size: 18px;
        }
        .footer {
            background: #fff3e0;
            padding: 20px;
            text-align: center;
            border-radius: 0 0 10px 10px;
            margin-top: 20px;
            font-size: 14px;
            color: #666;
        }
        .button {
            display: inline-block;
            background: #f57c00;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="lotus">🪷</div>
        <h1>{$monastery_name}</h1>
        <p>Healthcare & Donation Management</p>
    </div>
    
    <div class="content">
        <h2>Dear {$donor_name},</h2>
        
        <p>May you be blessed with good health, happiness, and prosperity!</p>
        
        <p>We are deeply grateful for your generous donation to {$monastery_name}. Your support helps us continue our mission of providing healthcare services and maintaining the monastery for the benefit of all beings.</p>
        
        <div class="amount-box">
            <h2>Rs. {$amount}</h2>
            <p style="margin: 0;">Total Donation Amount</p>
        </div>
        
        <div class="details">
            <table>
                <tr>
                    <td>Receipt Number:</td>
                    <td>{$receipt_no}</td>
                </tr>
                <tr>
                    <td>Donation Date:</td>
                    <td>{$date}</td>
                </tr>
                <tr>
                    <td>Category:</td>
                    <td>{$category}</td>
                </tr>
                <tr>
                    <td>Payment Method:</td>
                    <td>{$payment_method}</td>
                </tr>
HTML;

    $ref_number = $donation['reference_number'] ?? $donation['bank_reference'] ?? '';
    if (!empty($ref_number)) {
        $ref = htmlspecialchars($ref_number);
        $html .= <<<HTML
                <tr>
                    <td>Reference Number:</td>
                    <td>{$ref}</td>
                </tr>
HTML;
    }

    $html .= <<<HTML
            </table>
        </div>
        
        <p><strong>Your donation will be used for:</strong></p>
        <ul>
            <li>Healthcare services for monks and the community</li>
            <li>Maintenance of medical facilities</li>
            <li>Monastery upkeep and improvements</li>
            <li>Supporting the spiritual and physical wellbeing of all</li>
        </ul>
        
        <div class="blessing">
            "Dānaṃ dadanti saddhāya, sīlena saṃvareṇa ca"<br>
            <small>(Giving with faith and virtue brings great merit)</small>
        </div>
        
        <p style="text-align: center;">
            <a href="{$monastery_website}generate_receipt.php?id={$donation['donation_id']}" class="button">
                📄 View Your Receipt
            </a>
        </p>
        
        <p><strong>Tax Deduction:</strong> This receipt may be used for tax deduction purposes. Please consult with your tax advisor for eligibility.</p>
        
        <p>If you have any questions or would like to learn more about our programs, please don't hesitate to contact us.</p>
        
        <p>With deepest gratitude,<br>
        <strong>{$monastery_name}</strong></p>
    </div>
    
    <div class="footer">
        <p><strong>Theruwan Saranai!</strong> 🙏</p>
        <p>May the Triple Gem bless you!</p>
        <hr style="border: none; border-top: 1px solid #ddd; margin: 15px 0;">
        <p>
            <strong>{$monastery_name}</strong><br>
            Email: info@seelasuwa.lk | Phone: +94 XX XXX XXXX<br>
            Website: <a href="{$monastery_website}">{$monastery_website}</a>
        </p>
        <p style="font-size: 12px; color: #999;">
            This is an automated email. Please do not reply directly to this message.
        </p>
    </div>
</body>
</html>
HTML;

    return $html;
}
?>
