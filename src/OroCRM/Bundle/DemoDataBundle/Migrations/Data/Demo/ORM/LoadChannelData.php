<?php

namespace OroCRM\Bundle\DemoDataBundle\Migrations\Data\Demo\ORM;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\Persistence\ObjectManager;

use Oro\Bundle\OrganizationBundle\Entity\Organization;

use OroCRM\Bundle\ChannelBundle\Entity\Channel;
use Symfony\Component\DependencyInjection\ContainerInterface;

class LoadChannelData extends AbstractFixture
{
    /** @var ContainerInterface */
    protected $container;

    /** @var  EntityRepository */
    protected $organizationRepository;

    /**
     * {@inheritdoc}
     */
    public function getDependencies()
    {
        return ['OroCRM\Bundle\DemoDataBundle\Migrations\Data\Demo\ORM\LoadBusinessUnitData'];
    }

    /**
     * {@inheritDoc}
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
        $this->organizationRepository = $this->container->get('doctrine.orm.entity_manager')
            ->getRepository('OroOrganizationBundle:Organization');
    }

    /**
     * {@inheritDoc}
     */
    public function load(ObjectManager $om)
    {
        /** @var Organization $organization */
        $organization = $this->organizationRepository->findOneBy(['name' => 'default']);

        if (!$organization) {
            $organization = $this->organizationRepository->findOneBy(['name' => 'Acme, Inc']);
        }
        if (!$organization) {
            throw new \Exception('"default" company is not defined');
        }

        $this->persistChannel($om, $organization);
        $om->flush();
    }

    /**
     * @param ObjectManager $om
     * @param Organization  $organization
     */
    protected function persistChannel(ObjectManager $om, Organization $organization)
    {
        $channel = new Channel();
        $channel->setName('default');
        $channel->setDescription('some description');
        $channel->setOwner($organization);
        $om->persist($channel);

        $this->addReference('default_channel', $channel);
    }
}
