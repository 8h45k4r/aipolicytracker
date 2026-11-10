<?php

namespace App\Mail;

use App\Models\LicenceEnquiry;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Tells the contact address that a licence or coverage enquiry was recorded; a reply goes to the enquirer. */
class LicenceEnquiryMail extends Mailable
{
    public function __construct(public LicenceEnquiry $enquiry) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Licence enquiry: '.$this->enquiry->tierLabel().' · '.$this->enquiry->organization,
            replyTo: [new Address($this->enquiry->email, $this->enquiry->name)],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.site.licence-enquiry', text: 'emails.site.licence-enquiry-text', with: [
            'adminUrl' => route('backend.admin.enquiries.index'),
            'unsubscribeUrl' => route('backend.admin.settings'),
        ]);
    }
}
