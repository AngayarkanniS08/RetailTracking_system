<?php
namespace Modules\Auth\Service;

use Modules\Auth\DTO\ForgotPasswordDTO;
use Modules\Auth\Repository\Contract\UserRepositoryInterface;
use Modules\Auth\Repository\Contract\PasswordResetRepositoryInterface;
use Modules\Auth\validation\ValidationException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class ForgotPasswordService {
    private UserRepositoryInterface $userRepo;
    private PasswordResetRepositoryInterface $resetRepo;
    
    public function __construct(
        UserRepositoryInterface $userRepo,
        PasswordResetRepositoryInterface $resetRepo
    ) {
        $this->userRepo = $userRepo;
        $this->resetRepo = $resetRepo;
    }

    /**
     * @throws ValidationException
     */
    
    public function sendResetLink(ForgotPasswordDTO $dto): void {
        $user = $this->userRepo->findByEmail($dto->email);
        if (!$user) {
            // Always return generic message for security
            return;
        }
        
        // Delete any existing reset tokens for this user
        $this->resetRepo->deleteByUserId($user['id']);

        // Generate token and save to DB FIRST, so the link is always valid
        $token = bin2hex(random_bytes(32));
        // Use UTC explicitly so PostgreSQL (TIMESTAMPTZ) interprets the offset correctly
        $expiresAt = new \DateTimeImmutable('+1 hour', new \DateTimeZone('UTC'));
        $this->resetRepo->create($user['id'], $token, $expiresAt);

        // Now send the email with the token
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';      // Your SMTP server
            $mail->SMTPAuth   = true;
            $mail->Username   = 'angayarkannisurya@gmail.com';
            $mail->Password   = 'wvwz cyvg rlgx xsip';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            
            $mail->setFrom('no-reply@yoursystem.com', 'Inventory System');
            $mail->addAddress($user['email'], $user['username']);
            $mail->Subject = 'Password Reset Request';
            $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/index.php?action=reset_password&token=" . urlencode($token);
            $mail->Body    = "Click this link to reset your password: $resetLink\n\nThis link expires in 1 hour.";
            $mail->send();
        } catch (Exception $e) {
            // Log error
            error_log("Mail could not be sent. Error: {$mail->ErrorInfo}");
        }
        
    }
}