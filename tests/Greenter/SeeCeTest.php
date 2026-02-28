<?php

declare(strict_types=1);

namespace Tests\Greenter;

use Greenter\Model\DocumentInterface;
use Greenter\Model\Response\BillResult;
use Greenter\Model\Response\SummaryResult;
use Greenter\See;
use Greenter\Ws\Services\SunatEndpoints;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Greenter\Factory\CeFactoryBase;

#[Group('integration')]
class SeeCeTest extends CeFactoryBase
{
    #[DataProvider('providerBillDocs')]
    public function testSendBill(DocumentInterface $doc): void
    {
        /** @var BillResult $result */
        $result = $this->getSee(SunatEndpoints::RETENCION_BETA)->send($doc);

        $this->assertTrue($result->isSuccess());
        $this->assertNotNull($result->getCdrResponse());
        $this->assertStringContainsString('aceptado', $result->getCdrResponse()->getDescription());
    }

    public function testSendReversion(): void
    {
        $doc = $this->getReversion();
        /** @var SummaryResult $result */
        $result = $this->getSee(SunatEndpoints::RETENCION_BETA)->send($doc);

        $this->assertTrue($result->isSuccess());
        $this->assertNotEmpty($result->getTicket());
        $this->assertEquals(13, strlen($result->getTicket()));
    }

    #[DataProvider('providerBillDocs')]
    public function testGetXmlSigned(DocumentInterface $doc): void
    {
        $xmlSigned = $this->getSee(SunatEndpoints::RETENCION_BETA)->getXmlSigned($doc);

        $this->assertNotEmpty($xmlSigned);
    }

    public static function providerBillDocs(): array
    {
        $base = new self('providerBillDocs');

        return [
            [$base->getRetention()],
            [$base->getPerception()],
        ];
    }

    private function getSee(string $endpoint): See
    {
        $see = new See();
        $see->setService($endpoint);
        $see->setBuilderOptions([
            'strict_variables' => true,
            'optimizations'    => 0,
            'debug'            => true,
            'cache'            => false,
        ]);
        $see->setCredentials('20123456789MODDATOS', 'moddatos');
        $see->setCertificate(file_get_contents(__DIR__.'/../Resources/SFSCert.pem'));

        return $see;
    }
}
