<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Command;

use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Exception\MissingInputException;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * Prompts of the commands: the personal values (employer birth date, employee NIR) are never options, so that they
 * reach neither the shell history, nor the process list, nor a cron log (ADR 0007, lot 6 design §5.1, open point 6).
 *
 * Source: lot 6 design §5.1.
 */
final class CommandPrompts
{
    /**
     * A personal value read from a hidden prompt (never from argv, never echoed); '' for an empty answer.
     *
     * @throws InvalidOptionException in non-interactive mode, or when no answer is given
     */
    public static function hiddenValue(InputInterface $input, OutputInterface $output, QuestionHelper $helper, string $label): string
    {
        if (!$input->isInteractive()) {
            throw new InvalidOptionException(\sprintf('%s is read from a hidden prompt: run the command interactively.', $label));
        }
        $question = new Question($label . ': ');
        $question->setHidden(true);
        $question->setHiddenFallback(false);
        try {
            // @mago-expect analysis:mixed-assignment The question helper is untyped: the answer is narrowed below (an empty answer is null).
            $answer = $helper->ask($input, $output, $question);
        } catch (MissingInputException $failure) {
            throw new InvalidOptionException(\sprintf('%s was not given.', $label), 0, $failure);
        }

        return \is_string($answer) ? $answer : '';
    }
}
