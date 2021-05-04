<?php

/*
 * Copyright (c) 2011-2021 Lp Digital
 *
 * This file is part of BackBee Standalone.
 *
 * BackBee is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with BackBee Standalone. If not, see <https://www.gnu.org/licenses/>.
 */

namespace BackBee\Renderer\Helper;

use BackBee\ClassContent\AbstractClassContent;
use BackBee\ClassContent\ContentSet;
use BackBee\Renderer\AbstractRenderer;
use BackBee\Security\SecurityContext;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;

/**
 * Class bbcontent
 *
 * Helper providing HTML attributes to online-edited content.
 *
 * @package BackBee\Renderer\Helper
 *
 * @author e.chau <eric.chau@lp-digital.fr>
*  @author f.kroockmann <florian.kroockmann@lp-digital.fr>
 */
class bbcontent extends AbstractHelper
{
    /**
     * array that contains
     *
     * @var array
     */
    private $attributes;

    /**
     * the class content we are processing to get its attributes as string
     *
     * @var AbstractClassContent
     */
    private $content;

    /**
     * @var array
     */
    private $options;

    /**
     * bbcontent constructor.
     *
     * @param AbstractRenderer $renderer
     */
    public function __construct(AbstractRenderer $renderer)
    {
        parent::__construct($renderer);
        $this->reset();
    }

    /**
     * Return HTML formatted attribute for provided content.
     *
     * @param AbstractClassContent|null $content the content we want to generate its HTML attribute;
     *                                           if content is null, we get the current object set on current renderer
     * @param array                     $options
     *
     * @return string
     */
    public function __invoke(AbstractClassContent $content = null, array $options = []): string
    {
        $this->reset();

        $this->content = $content ?: $this->getRenderer()->getObject();
        $this->options = $options;

        $this->attributes['class'][] = 'bb-content';

        if ($this->isGranted()) {
            $result = $this->generateAttributesString();
        } else {
            $this->computeClassAttribute();
            $result = $this->getAttributesString();
        }

        return $result;
    }

    /**
     * @return boolean
     */
    private function isGranted(): bool
    {
        $securityContext = $this->getRenderer()->getApplication()->getSecurityContext();

        try {
            $result = ($securityContext instanceof SecurityContext &&
                null !== $this->getRenderer()->getApplication()->getBBUserToken() &&
                $securityContext->isGranted('VIEW', $this->content));
        } catch (AuthenticationCredentialsNotFoundException $e) {
            $result = false;
        }

        return $result;
    }

    /**
     * Resets options.
     */
    private function reset(): void
    {
        $this->attributes = [
            'class' => [],
            'data-bb-identifier' => null,
            'data-bb-maxentry' => null,
        ];

        $this->content = null;
        $this->options = [];
    }

    /**
     * @return string
     */
    private function generateAttributesString(): string
    {
        $this->computeClassAttribute();
        $this->computeDragAndDropAttributes();
        $this->computeIdentifierAttribute();
        $this->computeMaxEntryAttribute();
        $this->computeRendermodeAttribute();
        $this->computeAcceptAttribute();
        $this->computeRteAttribute();

        return $this->getAttributesString();
    }

    /**
     * Compute RTE attribute.
     */
    public function computeRteAttribute(): void
    {
        if (isset($this->options['rte-placeholder']) && true === $this->options['rte-placeholder']) {
            $this->attributes['data-cloud-placeholder'] = '';
        }
    }

    /**
     * Compute accept attribute.
     */
    public function computeAcceptAttribute(): void
    {
        if ($this->content instanceof ContentSet && 0 < count($this->content->getAccept())) {
            $this->attributes['data-accept'] = implode(',', $this->content->getAccept());
        }
    }

    /**
     * Compute render mode attribute.
     */
    public function computeRendermodeAttribute(): void
    {
        $renderer = $this->getRenderer();
        $this->attributes['data-rendermode'] = $renderer->getMode() ?? 'default';
    }

    /**
     * Computes class content drag and drop attributes and set it to attributes property array
     */
    private function computeClassAttribute(): void
    {
        $classes = $this->options['class'] ?? null;
        $paramClasses = $this->getRenderer()->getParam('class');

        if (null !== $classes || null !== $paramClasses) {
            $this->attributes['class'] = array_merge(
                $this->attributes['class'],
                $classes !== null ? is_array($classes) ? $classes : explode(' ', $classes) : [],
                $paramClasses !== null ? is_array($paramClasses) ? $paramClasses : explode(' ', $paramClasses) : []
            );
        }
    }

    /**
     * Computes class content drag and drop attributes and set it to attributes property array
     */
    private function computeDragAndDropAttributes(): void
    {
        $valid = false;
        if ($this->content instanceof ContentSet) {
            $valid = true === ($this->options['dropzone'] ?? true);
            $this->attributes['class'][] = 'bb-droppable';
        }

        $isElement = strpos(
            get_class($this->content),
            AbstractClassContent::CLASSCONTENT_BASE_NAMESPACE . 'Element\\'
        );
        $isContentSet = get_class($this->content) === AbstractClassContent::CLASSCONTENT_BASE_NAMESPACE . 'ContentSet';
        if (false === $isElement && false === $isContentSet) {
            $valid = true === ($this->options['draggable'] ?? true);
        }

        if ($valid) {
            $this->attributes['class'][] = 'bb-dnd';
        }
    }

    /**
     * Computes class content identifier attribute and set it to attributes property array
     */
    private function computeIdentifierAttribute(): void
    {
        $data = $this->content->jsonSerialize();
        $this->attributes['data-bb-identifier'] = str_replace('\\', '/', $data['type']) . '(' . $data['uid'] . ')';
    }

    /**
     * Computes classcontent maxentry attribute if classcontent is a ContentSet and sets it to attributes property array
     */
    private function computeMaxEntryAttribute(): void
    {
        if ($this->content instanceof ContentSet) {
            $this->attributes['data-bb-maxentry'] = $this->content->getMaxEntry();
        }
    }

    /**
     * @return string
     */
    private function getAttributesString(): string
    {
        $result = '';

        foreach ($this->attributes as $key => $value) {
            if (null !== $value) {
                $result .= " $key=\"" . (is_bool($value) ? ($value ? 'true' : 'false') : implode(
                    ' ',
                    (array)$value
                )) . '"';
            }
        }

        return $result;
    }
}
