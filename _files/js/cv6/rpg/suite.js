((window, document) =>
{
	"use strict";

	XF.cv6RpgRollCharacter = XF.Element.newHandler({
		eventNameSpace: 'cv6RpgRollCharacter',

		options: {
			rollables: null,
			prefillField: null,
			prefillComment: null,
			prefillModifier: null,
			prefillWireset: null,
			prefillTarget: null
		},

		rollablesMap: {},
		selectEl: null,
		chipsContainer: null,
		formEl: null,

		init: function ()
		{
			this.formEl = this.target.closest('form.js-rollform') || document;
			this.selectEl = this.target.querySelector('.js-cv6RpgCharacterSelect');
			this.chipsContainer = this.target.querySelector('.js-cv6RpgSkillChips');

			const rawJson = this.target.getAttribute('data-rollables');
			if (rawJson)
			{
				try
				{
					this.rollablesMap = JSON.parse(rawJson) || {};
				}
				catch (e)
				{
					this.rollablesMap = {};
				}
			}

			// Sync initial character selection with QuickReply character selector if present on the page
			if (this.selectEl && (!this.selectEl.value || this.selectEl.value === '0'))
			{
				const pageCharInput = document.querySelector('input[name="cv6_character_id"]');
				if (pageCharInput && parseInt(pageCharInput.value, 10) > 0)
				{
					const candidateId = String(pageCharInput.value);
					const opt = this.selectEl.querySelector('option[value="' + candidateId + '"]');
					if (opt)
					{
						this.selectEl.value = candidateId;
					}
				}
			}

			if (this.selectEl)
			{
				XF.on(this.selectEl, 'change', this.onCharacterChange.bind(this));
			}

			if (this.chipsContainer)
			{
				XF.on(this.chipsContainer, 'click', this.onChipClick.bind(this));
			}

			this.renderChips();
			this.applyInitialPrefill();
		},

		onCharacterChange: function ()
		{
			this.renderChips();
		},

		renderChips: function ()
		{
			if (!this.chipsContainer || !this.selectEl)
			{
				return;
			}

			const charId = this.selectEl.value;
			const fields = (charId && this.rollablesMap[charId]) ? this.rollablesMap[charId] : [];

			while (this.chipsContainer.firstChild)
			{
				this.chipsContainer.removeChild(this.chipsContainer.firstChild);
			}

			if (!fields || fields.length === 0)
			{
				this.chipsContainer.classList.add('u-hidden');
				return;
			}

			this.chipsContainer.classList.remove('u-hidden');

			fields.forEach(field =>
			{
				const btn = document.createElement('button');
				btn.type = 'button';
				btn.className = 'button button--link button--smaller cv6-rpg-skill-chip js-cv6RpgSkillChip';
				btn.setAttribute('data-field-id', field.field_id || '');
				btn.setAttribute('data-title', field.title || '');
				btn.setAttribute('data-value', field.value !== undefined ? String(field.value) : '0');
				btn.setAttribute('data-wireset', field.wireset || '');
				btn.setAttribute('data-target', field.param_target || 'modifier');

				const labelSpan = document.createElement('span');
				labelSpan.className = 'cv6-rpg-skill-chip__label';
				labelSpan.textContent = field.title || field.field_id;

				const valSpan = document.createElement('span');
				valSpan.className = 'cv6-rpg-skill-chip__val';
				valSpan.textContent = field.formatted_value || String(field.value || 0);

				btn.appendChild(labelSpan);
				btn.appendChild(document.createTextNode(' '));
				btn.appendChild(valSpan);

				this.chipsContainer.appendChild(btn);
			});
		},

		onChipClick: function (e)
		{
			const chip = e.target.closest('.js-cv6RpgSkillChip');
			if (!chip)
			{
				return;
			}
			e.preventDefault();

			const allChips = this.chipsContainer.querySelectorAll('.js-cv6RpgSkillChip');
			allChips.forEach(c => c.classList.remove('is-selected'));
			chip.classList.add('is-selected');

			const title = chip.getAttribute('data-title') || '';
			const val = chip.getAttribute('data-value') || '0';
			const wireset = chip.getAttribute('data-wireset') || '';
			const paramTarget = chip.getAttribute('data-target') || 'modifier';

			this.applySkillToForm(title, val, wireset, paramTarget);
		},

		applyInitialPrefill: function ()
		{
			const pending = window.cv6RpgPendingRollPrefill || null;
			if (pending)
			{
				window.cv6RpgPendingRollPrefill = null;
				if (pending.characterId && this.selectEl)
				{
					const opt = this.selectEl.querySelector('option[value="' + pending.characterId + '"]');
					if (opt)
					{
						this.selectEl.value = String(pending.characterId);
						this.renderChips();
					}
				}
				this.applySkillToForm(
					pending.comment || '',
					pending.modifier !== undefined ? String(pending.modifier) : '0',
					pending.wireset || '',
					pending.target || 'modifier'
				);
				return;
			}

			const pComment = this.target.getAttribute('data-prefill-comment') || '';
			const pMod = this.target.getAttribute('data-prefill-modifier') || '';
			const pWs = this.target.getAttribute('data-prefill-wireset') || '';
			const pTarget = this.target.getAttribute('data-prefill-target') || 'modifier';

			if (pComment !== '' || pWs !== '')
			{
				this.applySkillToForm(pComment, pMod, pWs, pTarget);
			}
		},

		applySkillToForm: function (title, val, wireset, paramTarget)
		{
			let activeWs = wireset;
			if (activeWs)
			{
				const tabLink = this.formEl.querySelector('.tabs-tab[data-wireset="' + activeWs + '"]');
				if (tabLink)
				{
					tabLink.click();
				}
			}

			if (!activeWs)
			{
				const activeWsInput = this.formEl.querySelector('.js-active-wireset');
				if (activeWsInput && activeWsInput.value)
				{
					activeWs = activeWsInput.value;
				}
			}

			if (!activeWs)
			{
				return;
			}

			if (title !== '')
			{
				const commentInput = this.formEl.querySelector('input[name="roll[' + activeWs + '][comment]"]');
				if (commentInput)
				{
					commentInput.value = title;
				}
			}

			if (val !== '')
			{
				let targetInput = null;
				if (paramTarget && paramTarget !== 'modifier')
				{
					targetInput = this.formEl.querySelector('input[name="roll[' + activeWs + '][options][' + paramTarget + ']"]');
				}
				if (!targetInput)
				{
					targetInput = this.formEl.querySelector('input[name="roll[' + activeWs + '][modifier]"]');
				}
				if (targetInput)
				{
					targetInput.value = val;
				}
			}

			// Ensure at least 1 die is selected on the primary die of the active wireset if all dice inputs are 0/empty
			const pane = this.formEl.querySelector('#dice-roller-' + activeWs);
			if (pane)
			{
				const diceInputs = pane.querySelectorAll('input.cv6--dicepreview');
				let totalDice = 0;
				diceInputs.forEach(inp =>
				{
					totalDice += parseInt(inp.value || '0', 10) || 0;
				});
				if (totalDice === 0 && diceInputs.length > 0)
				{
					diceInputs[0].value = '1';
				}
			}
		}
	});

	XF.cv6RpgQuickRollClick = XF.Click.newHandler({
		eventNameSpace: 'cv6RpgQuickRollClick',

		options: {
			characterId: null,
			fieldId: null,
			comment: null,
			modifier: null,
			wireset: null,
			target: null
		},

		init: function () {},

		click: function (e)
		{
			const rollBtn = document.querySelector('.button--rollDice[data-xf-init*="cv6DiceRoll"]');
			if (!rollBtn)
			{
				return;
			}

			e.preventDefault();

			window.cv6RpgPendingRollPrefill = {
				characterId: this.options.characterId || this.target.getAttribute('data-character-id') || '',
				fieldId: this.options.fieldId || this.target.getAttribute('data-field-id') || '',
				comment: this.options.comment || this.target.getAttribute('data-comment') || '',
				modifier: this.options.modifier !== null ? this.options.modifier : (this.target.getAttribute('data-modifier') || '0'),
				wireset: this.options.wireset || this.target.getAttribute('data-wireset') || '',
				target: this.options.target || this.target.getAttribute('data-target') || 'modifier'
			};

			const handler = XF.Element.getHandler(rollBtn, 'cv6DiceRoll');
			if (handler && handler.drOverlay && handler.drOverlay.overlay)
			{
				const rpgEl = handler.drOverlay.overlay.querySelector('[data-xf-init*="cv6-rpg-roll-character"]');
				if (rpgEl)
				{
					const rpgHandler = XF.Element.getHandler(rpgEl, 'cv6-rpg-roll-character');
					if (rpgHandler)
					{
						rpgHandler.applyInitialPrefill();
					}
				}
			}

			rollBtn.click();
		}
	});

	XF.Element.register('cv6-rpg-roll-character', 'XF.cv6RpgRollCharacter');
	XF.Click.register('cv6-rpg-quick-roll', 'XF.cv6RpgQuickRollClick');

})(window, document);
