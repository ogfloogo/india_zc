<?php

namespace app\admin\command;

use app\admin\model\finance\UserCash;
use app\admin\model\finance\UserRecharge;
use app\admin\model\sys\CheckReport;
use app\admin\model\User;
use app\admin\model\user\UserAward;
use app\admin\model\user\UserMoneyLog;
use think\cache\driver\Redis;
use think\Config;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\Db;
use think\Exception;
use think\Loader;
use think\Log;

class BuildLang extends Command
{
    protected $model = null;

    protected function configure()
    {
        $this->setName('BuildLang')
            ->setDescription('BuildLang');
        //要执行的controller必须一样，不适用模糊查询
    }

    protected function execute(Input $input, Output $output)
    {
        $lang = $this->init();
        $this->runLang($lang);
    }


    protected function runLang($lang)
    {
        $lang_list = array_keys($lang);
        print_r($lang_list);
        foreach ($lang['english'] as $en_key => $msg) {
            $key = str_replace(' ', '_', $en_key);
            $key = str_replace('.', '', $key);
            echo lcfirst($key);
            echo "\n";
            $key = lcfirst($key);
            $msg_list = [];
            foreach ($lang_list as $l) {
                $msg_list[$l] = $lang[$l][$en_key];
            }
            $data = $this->buildData($key, $msg, $msg_list);
            print_r($data);
        }

        // foreach ($lang as $l => $msg_list) {
        // }
    }

    protected function buildData($key, $en_msg, $msg_list)
    {
        $data['group'] = 'lang';
        $data['type'] = 'array';
        $data['name'] = $key;
        $data['title'] = $en_msg;
        $data['setting'] = '{\"table\":\"\",\"conditions\":\"\",\"key\":\"语言\",\"value\":\"显示文字\"}';
        $data['value'] = json_encode($msg_list, JSON_UNESCAPED_UNICODE);
        $data['content'] = '';
        $data['tip'] = '';
        $data['rule'] = '';
        $data['visible'] = '';
        $data['extend'] = '';
        return $data;
    }

    protected function init()
    {
        $lang['english'] =  [
            'Please log in again'               => 'Please log in again',
            'Transmission failed. The phone number has been registered'                     => 'Transmission failed. The phone number has been registered',
            'The phone number cannot be empty'                     => 'The phone number cannot be empty',
            'send successfully'                       => 'send successfully',
            'send failed'                      => 'send failed',
            'Sending frequent'                          => 'Sending frequent',
            'parameter error'                          => 'parameter error',
            'OTP is incorrect'                         => 'OTP is incorrect',
            'Inconsistent passwords'                        => 'Inconsistent passwords',
            'The phone number has been registered'                    => 'The phone number has been registered',
            'registered successfully'                            => 'registered successfully',
            'fail to register'                            => 'fail to register',
            'login successfully'                            => 'login successfully',
            'login failure'                            => 'login failure',
            'The phone number is not registered'                        => 'The phone number is not registered',
            'wrong password'                              => 'wrong password',
            'Logout successful'                              => 'Logout successful',
            'Password reset failed'                              => 'Password reset failed',
            'Password reset succeeded'                              => 'Password reset succeeded',
            'User already exists'                               => 'User already exists',
            'The user does not exist'                               => 'The user does not exist',
            'Payment channels do not exist'                            => 'Payment channels do not exist',
            'The payment channel is not opened'                        => 'The payment channel is not opened',
            'Minimum recharge amount'                          => 'Minimum recharge amount',
            'Maximum recharge amount'                          => 'Maximum recharge amount',
            'payment failure'                              => 'payment failure',
            'The request is successful'                              => 'The request is successful',
            'Your balance is not enough'                              => 'Your balance is not enough',
            'You have reached the max of group-buying times today for your current level'                    => 'You have reached the max of group-buying times today for your current level',
            'Reached the max create group-buying times today'                    => 'Reached the max create group-buying times today',
            'order failed'                              => 'order failed',
            'order successfully'                              => 'order successfully',
            'operation failure'                              => 'operation failure',
            'operate successfully'                              => 'operate successfully',
            'Wrong withdrawal password'                          => 'Wrong withdrawal password',
            'The Min withdrawal amount is'                => 'The Min withdrawal amount is',
            'The max withdrawal amount once'                => 'The max withdrawal amount once',
            'Withdraw up to 3 times a day'                      => 'Withdraw up to 3 times a day',
            'Insufficient withdrawable balance'                       => 'Insufficient withdrawable balance',
            'The recharge channel does not exist'                       => 'The recharge channel does not exist',
            'invalid account'                            => 'invalid account',
            'The password must be the number'                          => 'The password must be the number',
            'Requests are too frequent'                        => 'Requests are too frequent',
            'Illegal IP has been restricted from logging in'                            => 'Illegal IP has been restricted from logging in',
            'This item is out of stock, please check later'                              => 'This item is out of stock, please check later',
            'Authentication failed. Please update the latest app'                       => 'Authentication failed. Please update the latest app',
            'verify successfully'                          => 'verify successfully',
            'Your remaining balance needs to be greater than 99 pesos for the first withdrawal'    => 'Your remaining balance needs to be greater than 99 pesos for the first withdrawal',
            'Temporarily unable to place an order' => 'There are 12 opportunities to place an order per day. After the last order is completed, it will take 24 hours to refresh the available times.',
            'Experience has expired' => 'Experience has expired',
            'The first digit of the phone number cannot be 0' => 'The first digit of the phone number cannot be 0',
        ];
        $lang['india'] = [
            'Please log in again'               => 'कृपया फिर भाग लें',
            'Transmission failed. The phone number has been registered'                     => 'ट्रांसमिशन विफल रहा। फोन नंबर पंजीकृत किया गया है',
            'The phone number cannot be empty'                     => 'फ़ोन नंबर खाली नहीं हो सकता',
            'send successfully'                       => 'सफलतापूर्वक भेजें',
            'send failed'                      => 'भेजना विफल रहा',
            'Sending frequent'                          => 'बार-बार भेजना',
            'parameter error'                          => 'पैरामीटर त्रुटि',
            'OTP is incorrect'                         => 'OTP गलत है',
            'Inconsistent passwords'                        => 'असंगत पासवर्ड',
            'The phone number has been registered'                    => 'फोन नंबर पंजीकृत किया गया है',
            'registered successfully'                            => 'सफलतापूर्वक पंजीकृत',
            'fail to register'                            => 'पंजीकरण करने में विफल',
            'login successfully'                            => 'सफलतापूर्वक लॉगिन करें',
            'login failure'                            => 'लॉगिन विफलता',
            'The phone number is not registered'                        => 'फोन नंबर पंजीकृत नहीं है',
            'wrong password'                              => 'गलत पासवर्ड',
            'Logout successful'                              => 'लॉगआउट सफल रहा',
            'Password reset failed'                              => 'पासवर्ड रीसेट विफल रहा',
            'Password reset succeeded'                              => 'पासवर्ड रीसेट करना सफल रहा',
            'User already exists'                               => 'उपयोगकर्ता पहले से मौजूद है',
            'The user does not exist'                               => 'उपभोक्ता अस्तित्व मे नहीं है',
            'Payment channels do not exist'                            => 'भुगतान चैनल मौजूद नहीं हैं',
            'The payment channel is not opened'                        => 'भुगतान चैनल नहीं खोला गया है',
            'Minimum recharge amount'                          => 'न्यूनतम रिचार्ज राशि',
            'Maximum recharge amount'                          => 'अधिकतम रिचार्ज राशि',
            'payment failure'                              => 'भुगतान विफलता',
            'The request is successful'                              => 'अनुरोध सफल है',
            'Your balance is not enough'                              => 'आपका संतुलन पर्याप्त नहीं है',
            'You have reached the max of group-buying times today for your current level'                    => 'आज अधिकतम समूह-खरीदारी के समय पर पहुंच गया',
            'Reached the max create group-buying times today'                    => 'आज अधिकतम समूह-खरीद समय तक पहुंच गया',
            'order failed'                              => 'आदेश विफल',
            'order successfully'                              => 'आदेश सफलतापूर्वक',
            'operation failure'                              => 'संचालन विफलता',
            'operate successfully'                              => 'सफलतापूर्वक संचालित करें',
            'Wrong withdrawal password'                          => 'गलत निकासी पासवर्ड',
            'The Min withdrawal amount is'                => 'न्यूनतम निकासी राशि है',
            'The max withdrawal amount once'                => 'अधिकतम निकासी राशि एक बार',
            'Withdraw up to 3 times a day'                      => 'दिन में 3 बार तक निकासी करें',
            'Insufficient withdrawable balance'                       => 'अपर्याप्त निकासी योग्य शेष राशि',
            'The recharge channel does not exist'                       => 'रिचार्ज चैनल मौजूद नहीं है',
            'invalid account'                            => 'अवैध खाता',
            'The password must be the number'                          => 'पासवर्ड नंबर होना चाहिए',
            'Requests are too frequent'                        => 'अनुरोध बहुत बार-बार होते हैं',
            'Illegal IP has been restricted from logging in'                            => 'अवैध आईपी को लॉग इन करने से प्रतिबंधित कर दिया गया है',
            'This item is out of stock, please check later'                              => 'यह आइटम स्टॉक में नहीं है, कृपया बाद में देखें',
            'Authentication failed. Please update the latest app'                       => 'प्रमाणीकरण विफल होना। कृपया नवीनतम ऐप को अपडेट करें',
            'verify successfully'                          => 'सफलतापूर्वक सत्यापित करें',
            'Your remaining balance needs to be greater than 99 pesos for the first withdrawal'    => 'पहली बार निकासी के लिए आपकी शेष राशि 99 पेसो से अधिक होनी चाहिए',
            'Temporarily unable to place an order' => 'हर दिन ऑर्डर देने के 12 मौके हैं। अंतिम आदेश दिए जाने के 24 घंटे बाद उपलब्ध अवसरों को ताज़ा किया जाएगा।',
            'Experience has expired' => 'परीक्षण निधि समाप्त हो गई है',
            'The first digit of the phone number cannot be 0' => 'The first digit of the phone number cannot be 0'
        ];

        $lang['spain'] = [
            'Please log in again'               => 'Por favor inicia sesión de nuevo',
            'Transmission failed. The phone number has been registered'                     => 'La transmisión falló. El número de teléfono ha sido registrado.',
            'The phone number cannot be empty'                     => 'El número de teléfono no puede estar vacío',
            'send successfully'                       => 'enviar con éxito',
            'send failed'                      => 'enviar falló',
            'Sending frequent'                          => 'Envío frecuente',
            'parameter error'                          => 'error de parametro',
            'OTP is incorrect'                         => 'OTP es incorrecto',
            'Inconsistent passwords'                        => 'Contraseñas inconsistentes',
            'The phone number has been registered'                    => 'El número de teléfono ha sido registrado.',
            'registered successfully'                            => 'Registrado correctamente',
            'fail to register'                            => 'fallar al registrarse',
            'login successfully'                            => 'iniciar sesión con éxito',
            'login failure'                            => 'fallo de inicio de sesión',
            'The phone number is not registered'                        => 'El número de teléfono no está registrado',
            'wrong password'                              => 'contraseña incorrecta',
            'Logout successful'                              => 'Cierre de sesión exitoso',
            'Password reset failed'                              => 'Falló el restablecimiento de contraseña',
            'Password reset succeeded'                              => 'Restablecimiento de contraseña exitoso',
            'User already exists'                               => 'El usuario ya existe',
            'The user does not exist'                               => 'El usuario no existe',
            'Payment channels do not exist'                            => 'Los canales de pago no existen',
            'The payment channel is not opened'                        => 'No se abre el canal de pago',
            'Minimum recharge amount'                          => 'Importe mínimo de recarga',
            'Maximum recharge amount'                          => 'Importe máximo de recarga',
            'payment failure'                              => 'fallo de pago',
            'The request is successful'                              => 'La solicitud es exitosa',
            'Your balance is not enough'                              => 'Tu saldo no es suficiente',
            'You have reached the max of group-buying times today for your current level'                    => 'Alcanzó los tiempos máximos de compra grupal hoy',
            'Reached the max create group-buying times today'                    => 'Alcanzó el tiempo máximo de creación de compras grupales hoy',
            'order failed'                              => 'pedido fallido',
            'order successfully'                              => 'pedido con éxito',
            'The request is successful'                              => 'La solicitud es exitosa',
            'operation failure'                              => 'falla de operación',
            'operate successfully'                              => 'operar con éxito',
            'Wrong withdrawal password'                          => 'Contraseña de retiro incorrecta',
            'The Min withdrawal amount is'                => 'El monto mínimo de retiro es',
            'The max withdrawal amount once'                => 'La cantidad máxima de retiro una vez',
            'Withdraw up to 3 times a day'                      => 'Retirar hasta 3 veces al día',
            'Insufficient withdrawable balance'                       => 'Saldo disponible insuficiente',
            'The recharge channel does not exist'                       => 'El canal de recarga no existe',
            'invalid account'                            => 'cuenta no válida',
            'The password must be the number'                          => 'La contraseña debe ser el número',
            'Requests are too frequent'                        => 'Las solicitudes son demasiado frecuentes.',
            'Illegal IP has been restricted from logging in'                            => 'Se ha restringido la IP ilegal para iniciar sesión',
            'This item is out of stock, please check later'                              => 'Este artículo está agotado, compruébalo más tarde.',
            'Authentication failed. Please update the latest app'                       => 'La autenticación falló. Actualice la última aplicación.',
            'verify successfully'                          => 'verificar con éxito',
            'Your remaining balance needs to be greater than 99 pesos for the first withdrawal'    => 'Your remaining balance needs to be greater than 99 pesos for the first withdrawal',

            'Temporarily unable to place an order' => 'Temporalmente incapaz de realizar un pedido',
            'Experience has expired' => 'El fondo de prueba ha caducado',
            'The first digit of the phone number cannot be 0' => 'The first digit of the phone number cannot be 0',

        ];

        $lang['portugal'] = [
            'Please log in again'               => 'Por favor faça login novamente',
            'Transmission failed. The phone number has been registered'                     => 'A transmissão falhou. O número de telefone foi registrado',
            'The phone number cannot be empty'                     => 'O número de telefone não pode estar vazio',
            'send successfully'                       => 'enviar com sucesso',
            'send failed'                      => 'envio falhou',
            'Sending frequent'                          => 'Envio frequente',
            'parameter error'                          => 'erro de parâmetro',
            'OTP is incorrect'                         => 'OTP está incorreto',
            'Inconsistent passwords'                        => 'Senhas inconsistentes',
            'The phone number has been registered'                    => 'O número de telefone foi registrado',
            'registered successfully'                            => 'Registrado com sucesso',
            'fail to register'                            => 'falha ao registrar',
            'login successfully'                            => 'login com sucesso',
            'login failure'                            => 'falha de login',
            'The phone number is not registered'                        => 'O número de telefone não está registrado',
            'wrong password'                              => 'senha incorreta',
            'Logout successful'                              => 'Sair com sucesso',
            'Password reset failed'                              => 'Falha na redefinição de senha',
            'Password reset succeeded'                              => 'Redefinição de senha bem-sucedida',
            'User already exists'                               => 'Usuário já existe',
            'The user does not exist'                               => 'O usuário não existe',
            'Payment channels do not exist'                            => 'Canais de pagamento não existem',
            'The payment channel is not opened'                        => 'O canal de pagamento não está aberto',
            'Minimum recharge amount'                          => 'Quantidade mínima de recarga',
            'Maximum recharge amount'                          => 'Quantidade máxima de recarga',
            'payment failure'                              => 'falha de pagamento',
            'The request is successful'                              => 'A solicitação foi bem-sucedida',
            'Your balance is not enough'                              => 'Seu saldo não é suficiente',
            'You have reached the max of group-buying times today for your current level'                    => 'Atingiu o máximo de tempos de compra em grupo hoje',
            'Reached the max create group-buying times today'                    => 'Atingiu o máximo de tempos de compra em grupo para criar hoje',
            'order failed'                              => 'ordem falhada',
            'order successfully'                              => 'pedido com sucesso',
            'operation failure'                              => 'falha de operação',
            'operate successfully'                              =>  'operar com sucesso',
            'Wrong withdrawal password'                          => 'Senha de retirada errada',
            'The Min withdrawal amount is'                => 'O valor mínimo de retirada é',
            'The max withdrawal amount once'                => 'A quantidade máxima de retirada uma vez',
            'Withdraw up to 3 times a day'                      => 'Retirar até 3 vezes ao dia',
            'Insufficient withdrawable balance'                       => 'Saldo extraível insuficiente',
            'The recharge channel does not exist'                       => 'canal de recarga não existe',
            'invalid account'                            => 'conta invalida',
            'The password must be the number'                          => 'A senha deve ser o número',
            'Requests are too frequent'                        => 'Os pedidos são muito frequentes',
            'Illegal IP has been restricted from logging in'                            => 'O IP ilegal foi impedido de fazer login',
            'This item is out of stock, please check later'                              => 'Este item está esgotado, verifique mais tarde',
            'Authentication failed. Please update the latest app'                       => 'A autenticação falhou. Atualize o aplicativo mais recente',
            'verify successfully'                          => 'verifique com sucesso',
            'Your remaining balance needs to be greater than 99 pesos for the first withdrawal'    => 'Seu saldo restante precisa ser superior a 99 pesos para o primeiro saque',
            'Temporarily unable to place an order' => 'Temporariamente incapaz de fazer um pedido',
            'Experience has expired' => 'A experiência expirou',
            'The first digit of the phone number cannot be 0' => 'O primeiro dígito do número de telefone não pode ser 0',
        ];
        return $lang;
    }
}
