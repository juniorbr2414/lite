<?php

declare(strict_types=1);

namespace Tests\Greenter;

use Greenter\Model\DocumentInterface;
use Greenter\Model\Response\BillResult;
use Greenter\Model\Sale\Invoice;
use Greenter\See;
use Greenter\Validator\ErrorCodeProviderInterface;
use Greenter\Ws\Services\SunatEndpoints;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\Group;
use Tests\Greenter\Factory\FeFactoryBase;

#[Group('integration')]
class SeeFeTest extends FeFactoryBase
{
    #[DataProvider('providerInvoiceDocsV21')]
    public function testSendInvoiceV21(DocumentInterface $doc): void
    {
        /** @var BillResult $result */
        $see = $this->getSee();
        $this->assertNotNull($see->getFactory());

        $result = $see->send($doc);

        $this->assertTrue($result->isSuccess());
        $this->assertNotNull($result->getCdrResponse());
        $this->assertEquals('0', $result->getCdrResponse()->getCode());
        $this->assertCount(0, $result->getCdrResponse()->getNotes());
    }

    public function testSendSummary(): string
    {
        $doc    = $this->getSummary();
        $result = $this->getSee()->send($doc);

        $this->assertTrue($result->isSuccess());
        $this->assertNotEmpty($result->getTicket());
        $this->assertEquals(13, strlen($result->getTicket()));

        return $result->getTicket();
    }

    #[Depends('testSendSummary')]
    public function testGetStatus(string $ticket): void
    {
        $result = $this->getSee()->getStatus($ticket);

        if ($result->getCode() !== '0') {
            $this->assertTrue(true);
            return;
        }

        $this->assertTrue($result->isSuccess());
        $this->assertNull($result->getError());
        $this->assertNotNull($result->getCdrResponse());
        $this->assertEquals('0', $result->getCdrResponse()->getCode());
        $this->assertCount(0, $result->getCdrResponse()->getNotes());
    }

    #[DataProvider('providerInvoiceDocsV21')]
    public function testGetXmlUnsigned(DocumentInterface $doc): void
    {
        $xml = $this->getSee()->getXmlUnsigned($doc);

        $this->assertNotEmpty($xml);
        $this->assertStringNotContainsString('<ds:Signature', $xml);
        $this->assertStringContainsString('<?xml', $xml);
    }

    #[DataProvider('providerInvoiceDocsV21')]
    public function testGetXmlSigned(DocumentInterface $doc): void
    {
        $xmlSigned = $this->getSee()->getXmlSigned($doc);

        $this->assertNotEmpty($xmlSigned);
    }

    public function testSendXml(): void
    {
        $see      = $this->getSee();
        $invoice  = $this->getInvoice();
        $xmlSigned = $see->getXmlSigned($invoice);

        $this->assertNotEmpty($xmlSigned);

        $result = $see->sendXml(Invoice::class, $invoice->getName(), $xmlSigned);

        $this->assertTrue($result->isSuccess());
        $this->assertNotNull($result->getCdrResponse());
        $this->assertEquals('0', $result->getCdrResponse()->getCode());
        $this->assertCount(0, $result->getCdrResponse()->getNotes());
    }

    public function testSendXmlFile(): void
    {
        $see      = $this->getSee();
        $invoice  = $this->getInvoice();
        $xmlSigned = $see->getXmlSigned($invoice);

        $result = $see->sendXmlFile($xmlSigned);

        $this->assertTrue($result->isSuccess());
        $this->assertNotNull($result->getCdrResponse());
        $this->assertEquals('0', $result->getCdrResponse()->getCode());
        $this->assertCount(0, $result->getCdrResponse()->getNotes());
    }

    public static function providerInvoiceDocsV21(): array
    {
        $base = new self('providerInvoiceDocsV21');

        return [
            [$base->getInvoice()],
            [$base->getCreditNote()],
            [$base->getDebitNote()],
        ];
    }

    private function getSee(): See
    {
        $see = new See();
        $see->setService(SunatEndpoints::FE_BETA);
        $see->setBuilderOptions([
            'strict_variables' => true,
            'optimizations'    => 0,
            'debug'            => true,
        ]);
        $see->setCachePath(null);
        $see->setCodeProvider($this->getErrorCodeProvider());
        $see->setClaveSOL('20123456789', 'MODDATOS', 'moddatos');
        $see->setCertificate(file_get_contents(__DIR__.'/../Resources/SFSCert.pem'));

        return $see;
    }

    private function getErrorCodeProvider(): ErrorCodeProviderInterface
    {
        $stub = $this->createMock(ErrorCodeProviderInterface::class);
        $stub->method('getValue')->willReturn('');

        return $stub;
    }
}
