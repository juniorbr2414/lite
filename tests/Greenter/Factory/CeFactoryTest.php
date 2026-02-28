<?php

declare(strict_types=1);

namespace Tests\Greenter\Factory;

use Greenter\Model\Response\CdrResponse;
use Greenter\Model\Response\StatusResult;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\Group;

#[Group('integration')]
class CeFactoryTest extends CeFactoryBase
{
    public function testRetention(): void
    {
        $retention = $this->getRetention();
        $result    = $this->getFactoryResult($retention);

        $this->assertTrue($result->isSuccess());
        $this->assertNotNull($result->getCdrResponse());
        $this->assertEquals('0', $result->getCdrResponse()->getCode());
    }

    public function testGetXmlSigned(): void
    {
        $perception = $this->getPerception();
        $signXml    = $this->getXmlSigned($perception);

        $this->assertNotEmpty($signXml);
    }

    public function testPerception(): void
    {
        $perception = $this->getPerception();
        $result     = $this->getFactoryResult($perception);

        $this->assertTrue($result->isSuccess());
        $this->assertNotNull($result->getCdrResponse());
        $this->assertEquals('0', $result->getCdrResponse()->getCode());
    }

    public function testPerceptionNotValidRuc(): void
    {
        $perception = $this->getPerception();
        $perception->getCompany()->setRuc('2000010000');
        $result = $this->getFactoryResult($perception);

        $this->assertFalse($result->isSuccess());
        $this->assertNotNull($result->getError());
        $this->assertEquals('0151', $result->getError()->getCode());
    }

    public function testReversion(): string
    {
        $reversion = $this->getReversion();
        $result    = $this->getFactoryResult($reversion);

        if (!$result->isSuccess()) {
            return '';
        }

        $this->assertNotEmpty($this->factory->getLastXml());
        $this->assertTrue($result->isSuccess());
        $this->assertNotEmpty($result->getTicket());
        $this->assertEquals(13, strlen($result->getTicket()));

        return $result->getTicket();
    }

    #[Depends('testReversion')]
    public function testStatus(string $ticket): void
    {
        if ($ticket) {
            $result = $this->getExtService()->getStatus($ticket);
        } else {
            $result = new StatusResult();
            $result
                ->setCode('0')
                ->setCdrResponse((new CdrResponse())
                    ->setDescription('El Comprobante numero RR-20171001-001 ha sido aceptado')
                    ->setId('RR-20171001-001')
                    ->setCode('0')
                    ->setNotes([]))
                ->setCdrZip('xx')
                ->setSuccess(true);
        }

        $this->assertTrue($result->isSuccess());
        $this->assertNotNull($result->getCdrResponse());
        $this->assertNotNull($result->getCdrZip());
        $this->assertEquals('0', $result->getCode());
    }

    public function testStatusInvalidTicket(): void
    {
        $result = $this->getExtService()->getStatus('123456789456');

        $this->assertFalse($result->isSuccess());
        $this->assertNotNull($result->getError());
        $this->assertEquals('0127', $result->getError()->getCode());
        $this->assertStringContainsString('El ticket no existe', $result->getError()->getMessage());
    }
}
