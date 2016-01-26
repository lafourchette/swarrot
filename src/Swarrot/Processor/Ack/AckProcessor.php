<?php

namespace Swarrot\Processor\Ack;

use Swarrot\Processor\ProcessorInterface;
use Swarrot\Processor\ConfigurableInterface;
use Swarrot\Broker\MessageProvider\MessageProviderInterface;
use Swarrot\Broker\Message;
use Psr\Log\LoggerInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AckProcessor implements ConfigurableInterface
{
    /**
     * @var ProcessorInterface
     */
    protected $processor;

    /**
     * @var MessageProviderInterface
     */
    protected $messageProvider;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param ProcessorInterface       $processor       Processor
     * @param MessageProviderInterface $messageProvider Message provider
     * @param LoggerInterface          $logger          Logger
     */
    public function __construct(ProcessorInterface $processor, MessageProviderInterface $messageProvider, LoggerInterface $logger = null)
    {
        $this->processor = $processor;
        $this->messageProvider = $messageProvider;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function process(Message $message, array $options)
    {
        try {
            $return = $this->processor->process($message, $options);
            $this->messageProvider->ack($message);

            $this->logger and $this->logger->info(
                '[Ack] Message #'.$message->getId().' have been correctly ack\'ed',
                array(
                    'swarrot_processor' => 'ack',
                )
            );

            return $return;
        } catch (\Exception $e) {
            $requeue = isset($options['requeue_on_error']) ? (boolean) $options['requeue_on_error'] : false;
            $this->messageProvider->nack($message, $requeue);

            $this->logger and $this->logger->warning(
                sprintf(
                    '[Ack] An exception occurred. Message #%d have been %s.',
                    $message->getId(),
                    $requeue ? 'requeued' : 'nack\'ed'
                ),
                array(
                    'swarrot_processor' => 'ack',
                    'exception' => $e,
                )
            );

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'requeue_on_error' => false,
        ));

        if (method_exists($resolver, 'setDefined')) {
            $resolver->setAllowedTypes('requeue_on_error', 'bool');
        } else {
            // BC for OptionsResolver < 2.6
            $resolver->setAllowedTypes(array(
                'requeue_on_error' => 'bool',
            ));
        }
    }
}
