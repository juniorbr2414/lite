<?php

declare(strict_types=1);

namespace Tests\Greenter\Factory;

use DOMDocument;
use DOMXPath;
use Greenter\Model\DocumentInterface;
use Greenter\Model\Response\BaseResult;
use Greenter\See;
use Greenter\Services\SenderInterface;

class FeFactoryXmlTest extends FeFactoryBase
{
    public function testGetXmlUnsignedInvoice(): void
    {
        $see     = new See();
        $see->setBuilderOptions(['cache' => false, 'strict_variables' => true]);
        $invoice = $this->getInvoice();

        $xml = $see->getXmlUnsigned($invoice);

        $this->assertNotEmpty($xml);
        $this->assertStringContainsString('<?xml', $xml);
        $this->assertStringNotContainsString('<ds:Signature', $xml);
        $xpt  = $this->getXpath($xml);
        $tipo = $xpt->query('//cbc:InvoiceTypeCode');
        $this->assertEquals(1, $tipo->length);
        $this->assertEquals($invoice->getTipoDoc(), $tipo->item(0)->nodeValue);
    }

    public function testInvoiceXml(): void
    {
        $invoice = $this->getInvoice();
        $this->getFactoryForXml($invoice);

        $xpt  = $this->getXpath($this->factory->getLastXml());
        $nodes = $xpt->query('//ds:Signature');
        $tipo  = $xpt->query('//cbc:InvoiceTypeCode');

        $this->assertEquals(1, $nodes->length);
        $this->assertEquals(1, $tipo->length);
        $this->assertEquals($invoice->getTipoDoc(), $tipo->item(0)->nodeValue);
    }

    public function testCreditNoteXml(): void
    {
        $creditNote = $this->getCreditNote();
        $this->getFactoryForXml($creditNote);

        $xpt  = $this->getXpath($this->factory->getLastXml());
        $nodes = $xpt->query('//ds:Signature');
        $tipo  = $xpt->query('//cbc:ResponseCode');

        $this->assertEquals(1, $nodes->length);
        $this->assertEquals(1, $tipo->length);
        $this->assertEquals($creditNote->getCodMotivo(), $tipo->item(0)->nodeValue);
    }

    public function testDebitNoteXml(): void
    {
        $debitNote = $this->getDebitNote();
        $this->getFactoryForXml($debitNote);

        $xpt  = $this->getXpath($this->factory->getLastXml());
        $nodes = $xpt->query('//ds:Signature');
        $tipo  = $xpt->query('//cbc:ResponseCode');

        $this->assertEquals(1, $nodes->length);
        $this->assertEquals(1, $tipo->length);
        $this->assertEquals($debitNote->getCodMotivo(), $tipo->item(0)->nodeValue);
    }

    public function testResumenXml(): void
    {
        $resumen = $this->getSummary();
        $this->getFactoryForXml($resumen);

        $xpt  = $this->getXpath($this->factory->getLastXml());
        $nodes = $xpt->query('//ds:Signature');
        $tipo  = $xpt->query('//sac:SummaryDocumentsLine');

        $this->assertEquals(1, $nodes->length);
        $this->assertEquals(count($resumen->getDetails()), $tipo->length);
    }

    public function testBajaXml(): void
    {
        $baja = $this->getVoided();
        $this->getFactoryForXml($baja);

        $xpt  = $this->getXpath($this->factory->getLastXml());
        $nodes = $xpt->query('//ds:Signature');
        $tipo  = $xpt->query('//sac:VoidedDocumentsLine');

        $this->assertEquals(1, $nodes->length);
        $this->assertEquals(count($baja->getDetails()), $tipo->length);
    }

    private function getXpath(string $xml): DOMXPath
    {
        $doc = new DOMDocument();
        $doc->loadXML($xml);

        return new DOMXPath($doc);
    }

    private function getFactoryForXml(DocumentInterface $document): void
    {
        $sender = $this->createStub(SenderInterface::class);
        $sender->method('send')
            ->willReturn((new BaseResult())->setSuccess(true));

        $builder = new $this->builders[get_class($document)]([
            'cache'            => false,
            'strict_variables' => true,
        ]);

        $this->factory
            ->setBuilder($builder)
            ->setSender($sender)
            ->send($document);
    }
}
