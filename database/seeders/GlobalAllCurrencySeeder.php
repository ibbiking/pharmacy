<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Currency\GlobalCurrency;

class GlobalAllCurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $currencies = [
            ['currency_code' => 'AED', 'name' => 'Emirati Dirham', 'symbol' => 'د.إ', 'exchange_rate' => 1],
            ['currency_code' => 'AFN', 'name' => 'Afghani', 'symbol' => '؋', 'exchange_rate' => 1],
            ['currency_code' => 'ALL', 'name' => 'Lek', 'symbol' => 'Lek', 'exchange_rate' => 1],
            ['currency_code' => 'ANG', 'name' => 'Netherlands Antillian Guilder', 'symbol' => 'ƒ', 'exchange_rate' => 1],
            ['currency_code' => 'ARS', 'name' => 'Argentine Peso', 'symbol' => '$', 'exchange_rate' => 1],
            ['currency_code' => 'AUD', 'name' => 'Australian Dollar', 'symbol' => '$', 'exchange_rate' => 1],
            ['currency_code' => 'AWG', 'name' => 'Aruban Guilder', 'symbol' => 'ƒ', 'exchange_rate' => 1],
            ['currency_code' => 'AZN', 'name' => 'Azerbaijanian Manat', 'symbol' => 'ман', 'exchange_rate' => 1],
            ['currency_code' => 'BAM', 'name' => 'Convertible Marks', 'symbol' => 'KM', 'exchange_rate' => 1],
            ['currency_code' => 'BDT', 'name' => 'Bangladeshi Taka', 'symbol' => '৳', 'exchange_rate' => 1],
            ['currency_code' => 'BBD', 'name' => 'Barbados Dollar', 'symbol' => '$', 'exchange_rate' => 1],
            ['currency_code' => 'BGN', 'name' => 'Bulgarian Lev', 'symbol' => 'лв', 'exchange_rate' => 1],
            ['currency_code' => 'BMD', 'name' => 'Bermudian Dollar', 'symbol' => '$', 'exchange_rate' => 1],
            ['currency_code' => 'BND', 'name' => 'Brunei Dollar', 'symbol' => '$', 'exchange_rate' => 1],
            ['currency_code' => 'BOB', 'name' => 'BOV Boliviano Mvdol', 'symbol' => '$b', 'exchange_rate' => 1],
            ['currency_code' => 'BRL', 'name' => 'Brazilian Real', 'symbol' => 'R$', 'exchange_rate' => 1],
            ['currency_code' => 'BSD', 'name' => 'Bahamian Dollar', 'symbol' => '$', 'exchange_rate' => 1],
            ['currency_code' => 'BWP', 'name' => 'Pula', 'symbol' => 'P', 'exchange_rate' => 1],
            ['currency_code' => 'BYR', 'name' => 'Belarussian Ruble', 'symbol' => '₽', 'exchange_rate' => 1],
            ['currency_code' => 'BZD', 'name' => 'Belize Dollar', 'symbol' => 'BZ$', 'exchange_rate' => 1],
            ['currency_code' => 'CAD', 'name' => 'Canadian Dollar', 'symbol' => '$', 'exchange_rate' => 1],
            ['currency_code' => 'CHF', 'name' => 'Swiss Franc', 'symbol' => 'CHF', 'exchange_rate' => 1],
            ['currency_code' => 'CLP', 'name' => 'CLF Chilean Peso Unidades de fomento', 'symbol' => '$', 'exchange_rate' => 1],
            ['currency_code' => 'CNY', 'name' => 'Yuan Renminbi', 'symbol' => '¥', 'exchange_rate' => 1],
            ['currency_code' => 'COP', 'name' => 'COU Colombian Peso Unidad de Valor Real', 'symbol' => '$', 'exchange_rate' => 1],
            ['currency_code' => 'CRC', 'name' => 'Costa Rican Colon', 'symbol' => '₡', 'exchange_rate' => 1],
            ['currency_code' => 'CUP', 'name' => 'CUC Cuban Peso Peso Convertible', 'symbol' => '₱', 'exchange_rate' => 1],
            ['currency_code' => 'CZK', 'name' => 'Czech Koruna', 'symbol' => 'Kč', 'exchange_rate' => 1],
            ['currency_code' => 'DKK', 'name' => 'Danish Krone', 'symbol' => 'kr', 'exchange_rate' => 1],
            ['currency_code' => 'DOP', 'name' => 'Dominican Peso', 'symbol' => 'RD$', 'exchange_rate' => 1],
            ['currency_code' => 'EGP', 'name' => 'Egyptian Pound', 'symbol' => '£', 'exchange_rate' => 1],
            ['currency_code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'exchange_rate' => 1],
            ['currency_code' => 'FJD', 'name' => 'Fiji Dollar', 'symbol' => '$', 'exchange_rate' => 1],
            ['currency_code' => 'FKP', 'name' => 'Falkland Islands Pound', 'symbol' => '£', 'exchange_rate' => 1],
            ['currency_code' => 'GBP', 'name' => 'Pound Sterling', 'symbol' => '£', 'exchange_rate' => 1],
            ['currency_code' => 'GIP', 'name' => 'Gibraltar Pound', 'symbol' => '£', 'exchange_rate' => 1],
            ['currency_code' => 'GTQ', 'name' => 'Quetzal', 'symbol' => 'Q', 'exchange_rate' => 1],
            ['currency_code' => 'GYD', 'name' => 'Guyana Dollar', 'symbol' => '$', 'exchange_rate' => 1],
            ['currency_code' => 'HKD', 'name' => 'Hong Kong Dollar', 'symbol' => '$', 'exchange_rate' => 1],
            ['currency_code' => 'HNL', 'name' => 'Lempira', 'symbol' => 'L', 'exchange_rate' => 1],
            ['currency_code' => 'HRK', 'name' => 'Croatian Kuna', 'symbol' => 'kn', 'exchange_rate' => 1],
            ['currency_code' => 'HUF', 'name' => 'Forint', 'symbol' => 'Ft', 'exchange_rate' => 1],
            ['currency_code' => 'IDR', 'name' => 'Rupiah', 'symbol' => 'Rp', 'exchange_rate' => 1],
            ['currency_code' => 'ILS', 'name' => 'New Israeli Sheqel', 'symbol' => '₪', 'exchange_rate' => 1],
            ['currency_code' => 'IRR', 'name' => 'Iranian Rial', 'symbol' => '﷼', 'exchange_rate' => 1],
            ['currency_code' => 'ISK', 'name' => 'Iceland Krona', 'symbol' => 'kr', 'exchange_rate' => 1],
            ['currency_code' => 'JMD', 'name' => 'Jamaican Dollar', 'symbol' => 'J$', 'exchange_rate' => 1],
            ['currency_code' => 'JPY', 'name' => 'Yen', 'symbol' => '¥', 'exchange_rate' => 1],
            ['currency_code' => 'KGS', 'name' => 'Som', 'symbol' => 'лв', 'exchange_rate' => 1],
            ['currency_code' => 'KHR', 'name' => 'Riel', 'symbol' => '៛', 'exchange_rate' => 1],
            ['currency_code' => 'KPW', 'name' => 'North Korean Won', 'symbol' => '₩', 'exchange_rate' => 1],
            ['currency_code' => 'KRW', 'name' => 'Won', 'symbol' => '₩', 'exchange_rate' => 1],
            ['currency_code' => 'KYD', 'name' => 'Cayman Islands Dollar', 'symbol' => '$', 'exchange_rate' => 1],
            ['currency_code' => 'KZT', 'name' => 'Tenge', 'symbol' => 'лв', 'exchange_rate' => 1],
            ['currency_code' => 'LAK', 'name' => 'Kip', 'symbol' => '₭', 'exchange_rate' => 1],
            ['currency_code' => 'LBP', 'name' => 'Lebanese Pound', 'symbol' => '£', 'exchange_rate' => 1],
            ['currency_code' => 'LKR', 'name' => 'Sri Lanka Rupee', 'symbol' => '₨', 'exchange_rate' => 1],
            ['currency_code' => 'LRD', 'name' => 'Liberian Dollar', 'symbol' => '$', 'exchange_rate' => 1],
            ['currency_code' => 'LTL', 'name' => 'Lithuanian Litas', 'symbol' => 'Lt', 'exchange_rate' => 1],
            ['currency_code' => 'LVL', 'name' => 'Latvian Lats', 'symbol' => 'Ls', 'exchange_rate' => 1],
            ['currency_code' => 'MKD', 'name' => 'Denar', 'symbol' => 'ден', 'exchange_rate' => 1],
            ['currency_code' => 'MNT', 'name' => 'Tugrik', 'symbol' => '₮', 'exchange_rate' => 1],
            ['currency_code' => 'MUR', 'name' => 'Mauritius Rupee', 'symbol' => '₨', 'exchange_rate' => 1],
            ['currency_code' => 'MXN', 'name' => 'MXV Mexican Peso Mexican Unidad de Inversion (UDI]', 'symbol' => '$', 'exchange_rate' => 1],
            ['currency_code' => 'MYR', 'name' => 'Malaysian Ringgit', 'symbol' => 'RM', 'exchange_rate' => 1],
            ['currency_code' => 'MZN', 'name' => 'Metical', 'symbol' => 'MT', 'exchange_rate' => 1],
            ['currency_code' => 'NGN', 'name' => 'Naira', 'symbol' => '₦', 'exchange_rate' => 1],
            ['currency_code' => 'NIO', 'name' => 'Cordoba Oro', 'symbol' => 'C$', 'exchange_rate' => 1],
            ['currency_code' => 'NOK', 'name' => 'Norwegian Krone', 'symbol' => 'kr', 'exchange_rate' => 1],
            ['currency_code' => 'NPR', 'name' => 'Nepalese Rupee', 'symbol' => '₨', 'exchange_rate' => 1],
            ['currency_code' => 'NZD', 'name' => 'New Zealand Dollar', 'symbol' => '$', 'exchange_rate' => 1],
            ['currency_code' => 'OMR', 'name' => 'Rial Omani', 'symbol' => '﷼', 'exchange_rate' => 1],
            ['currency_code' => 'PAB', 'name' => 'USD Balboa US Dollar', 'symbol' => 'B/.', 'exchange_rate' => 1],
            ['currency_code' => 'PEN', 'name' => 'Nuevo Sol', 'symbol' => 'S/.', 'exchange_rate' => 1],
            ['currency_code' => 'PHP', 'name' => 'Philippine Peso', 'symbol' => 'Php', 'exchange_rate' => 1],
            ['currency_code' => 'PKR', 'name' => 'Pakistan Rupee', 'symbol' => '₨', 'exchange_rate' => 1],
            ['currency_code' => 'PLN', 'name' => 'Zloty', 'symbol' => 'zł', 'exchange_rate' => 1],
            ['currency_code' => 'PYG', 'name' => 'Guarani', 'symbol' => 'Gs', 'exchange_rate' => 1],
            ['currency_code' => 'QAR', 'name' => 'Qatari Rial', 'symbol' => '﷼', 'exchange_rate' => 1],
            ['currency_code' => 'RON', 'name' => 'New Leu', 'symbol' => 'lei', 'exchange_rate' => 1],
            ['currency_code' => 'RSD', 'name' => 'Serbian Dinar', 'symbol' => 'Дин.', 'exchange_rate' => 1],
            ['currency_code' => 'RUB', 'name' => 'Russian Ruble', 'symbol' => 'руб', 'exchange_rate' => 1],
            ['currency_code' => 'SAR', 'name' => 'Saudi Riyal', 'symbol' => '﷼', 'exchange_rate' => 1],
            ['currency_code' => 'SBD', 'name' => 'Solomon Islands Dollar', 'symbol' => '$', 'exchange_rate' => 1],
            ['currency_code' => 'SCR', 'name' => 'Seychelles Rupee', 'symbol' => '₨', 'exchange_rate' => 1],
            ['currency_code' => 'SEK', 'name' => 'Swedish Krona', 'symbol' => 'kr', 'exchange_rate' => 1],
            ['currency_code' => 'SGD', 'name' => 'Singapore Dollar', 'symbol' => '$', 'exchange_rate' => 1],
            ['currency_code' => 'SHP', 'name' => 'Saint Helena Pound', 'symbol' => '£', 'exchange_rate' => 1],
            ['currency_code' => 'SOS', 'name' => 'Somali Shilling', 'symbol' => 'S', 'exchange_rate' => 1],
            ['currency_code' => 'SRD', 'name' => 'Surinam Dollar', 'symbol' => '$', 'exchange_rate' => 1],
            ['currency_code' => 'SVC', 'name' => 'USD El Salvador Colon US Dollar', 'symbol' => '$', 'exchange_rate' => 1],
            ['currency_code' => 'SYP', 'name' => 'Syrian Pound', 'symbol' => '£', 'exchange_rate' => 1],
            ['currency_code' => 'THB', 'name' => 'Baht', 'symbol' => '฿', 'exchange_rate' => 1],
            ['currency_code' => 'TRY', 'name' => 'Turkish Lira', 'symbol' => '₺', 'exchange_rate' => 1],
            ['currency_code' => 'TTD', 'name' => 'Trinidad and Tobago Dollar', 'symbol' => 'TT$', 'exchange_rate' => 1],
            ['currency_code' => 'TWD', 'name' => 'New Taiwan Dollar', 'symbol' => 'NT$', 'exchange_rate' => 1],
            ['currency_code' => 'UAH', 'name' => 'Hryvnia', 'symbol' => '₴', 'exchange_rate' => 1],
            // United States Dollar — explicitly required; missing from the
            // original reference list this seeder was based on.
            ['currency_code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'exchange_rate' => 1],
            ['currency_code' => 'UYU', 'name' => 'UYI Uruguay Peso en Unidades Indexadas', 'symbol' => '$U', 'exchange_rate' => 1],
            ['currency_code' => 'UZS', 'name' => 'Uzbekistan Sum', 'symbol' => 'лв', 'exchange_rate' => 1],
            ['currency_code' => 'VEF', 'name' => 'Bolivar Fuerte', 'symbol' => 'Bs', 'exchange_rate' => 1],
            ['currency_code' => 'VND', 'name' => 'Dong', 'symbol' => '₫', 'exchange_rate' => 1],
            ['currency_code' => 'XCD', 'name' => 'East Caribbean Dollar', 'symbol' => '$', 'exchange_rate' => 1],
            ['currency_code' => 'YER', 'name' => 'Yemeni Rial', 'symbol' => '﷼', 'exchange_rate' => 1],
            ['currency_code' => 'ZAR', 'name' => 'Rand', 'symbol' => 'R', 'exchange_rate' => 1],
            ['currency_code' => 'IND', 'name' => 'Indian Rupee ', 'symbol' => '₹', 'exchange_rate' => 1],
            ['currency_code' => 'GHC', 'name' => 'Ghanaian Cedis', 'symbol' => '¢', 'exchange_rate' => 1],
            ['currency_code' => 'GGP', 'name' => 'Guernsey Pounds', 'symbol' => '£', 'exchange_rate' => 1],
            ['currency_code' => 'NAD', 'name' => 'Namibian Dollars', 'symbol' => '$', 'exchange_rate' => 1],
            ['currency_code' => 'IMP', 'name' => 'Manx Pounds', 'symbol' => '£', 'exchange_rate' => 1],
            ['currency_code' => 'JEP', 'name' => 'Jersey Pounds', 'symbol' => '£', 'exchange_rate' => 1],
            ['currency_code' => 'TVD', 'name' => 'Tuvaluan Dollars', 'symbol' => '$', 'exchange_rate' => 1],
            ['currency_code' => 'ZWD', 'name' => 'Zimbabwe Dollars', 'symbol' => 'Z$', 'exchange_rate' => 1],
        ];

        foreach ($currencies as $index => $currency) {
            echo $index . ' --- ' . $currency['name'] . ' --- ' . $currency['symbol'] . ' --- ' . $currency['currency_code'];
            echo "\n\r";

            // Global currencies only (business_id is null) — a business's own
            // custom currency with the same code is a separate row and is
            // never touched by this seeder.
            $currencyFound = GlobalCurrency::where([
                'business_id' => null,
                'currency_code' => $currency['currency_code'],
            ])->first();

            if ($currencyFound) {
                echo "currency found";
                echo "\n\r";
                continue;
            } else {
                echo "currency not found";
                echo "\n\r";
                GlobalCurrency::create($currency);
            }
        }
    }
}
