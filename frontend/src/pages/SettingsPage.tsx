import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { Input } from '@/components/ui/Field'
import { ConfirmDialog, Modal } from '@/components/ui/Modal'
import { EmptyState, LoadingState } from '@/components/ui/State'
import { Table, TableWrap, Td, Th } from '@/components/ui/Table'
import { qk, useRoles, useUnits } from '@/hooks/queries'
import { useAuth } from '@/hooks/useAuth'
import { useToast } from '@/hooks/useToast'
import { errorValidasi, pesanError } from '@/lib/api'
import { masterService } from '@/services/masterService'
import type { Unit } from '@/types'
import { kodeSatuan } from '@/utils/format'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Pencil, Plus, Trash2 } from 'lucide-react'
import { useState, type FormEvent } from 'react'

const SATUAN_KOSONG = { code: '', name: '' }

/** Master data aplikasi: peran pengguna dan satuan pekerjaan. */
export function SettingsPage() {
  const { user, punyaPeran } = useAuth()
  const toast = useToast()
  const queryClient = useQueryClient()
  const adminMode = punyaPeran('ADMIN')
  const { data: roles, isLoading: memuatRoles } = useRoles()
  const { data: units, isLoading: memuatUnits } = useUnits()

  const [formSatuan, setFormSatuan] = useState(false)
  const [satuan, setSatuan] = useState(SATUAN_KOSONG)
  const [satuanDiedit, setSatuanDiedit] = useState<Unit | null>(null)
  const [satuanDihapus, setSatuanDihapus] = useState<Unit | null>(null)
  const [errors, setErrors] = useState<Record<string, string>>({})

  const bukaFormSatuan = (unit: Unit | null = null) => {
    setSatuanDiedit(unit)
    setSatuan(unit ? { code: unit.code, name: unit.name } : SATUAN_KOSONG)
    setErrors({})
    setFormSatuan(true)
  }

  // Satuan tampil di Pekerjaan, Rencana, Progres, dan laporan; semuanya disegarkan setelah satuan berubah.
  const segarkanSatuan = async () => {
    await queryClient.invalidateQueries({ queryKey: qk.units })
    await queryClient.invalidateQueries({ queryKey: ['project'] })
    await queryClient.invalidateQueries({ queryKey: ['progress'] })
  }

  const simpanSatuan = useMutation({
    mutationFn: (payload: { code: string; name: string }) =>
      satuanDiedit ? masterService.updateUnit(satuanDiedit.id, payload) : masterService.createUnit(payload),
    onSuccess: async () => {
      toast.sukses(satuanDiedit ? 'Satuan pekerjaan berhasil diperbarui.' : 'Satuan pekerjaan berhasil ditambahkan.')
      await segarkanSatuan()
      setFormSatuan(false)
    },
    onError: (err) => {
      const validasi = errorValidasi(err)
      setErrors(validasi)
      toast.gagal(Object.keys(validasi).length > 0 ? 'Periksa kembali data yang kamu masukkan.' : pesanError(err))
    },
  })

  const hapusSatuan = useMutation({
    mutationFn: (id: number) => masterService.removeUnit(id),
    onSuccess: async () => {
      toast.sukses('Satuan pekerjaan berhasil dihapus.')
      setSatuanDihapus(null)
      await segarkanSatuan()
    },
    onError: (err) => {
      toast.gagal(pesanError(err))
      setSatuanDihapus(null)
    },
  })

  /** Satuan yang masih dipakai pekerjaan tidak dapat dihapus (backend ikut menolak). */
  const mintaHapusSatuan = (unit: Unit) => {
    const dipakai = unit.jumlah_pekerjaan ?? 0

    if (dipakai > 0) {
      toast.gagal(`Satuan "${kodeSatuan(unit.code)}" masih dipakai ${dipakai} pekerjaan sehingga tidak dapat dihapus.`)

      return
    }

    setSatuanDihapus(unit)
  }

  const kirimSatuan = (event: FormEvent) => {
    event.preventDefault()
    const validasi: Record<string, string> = {}
    const payload = { code: satuan.code.trim(), name: satuan.name.trim() }

    if (!payload.code) validasi.code = 'Kode satuan wajib diisi.'
    if (!payload.name) validasi.name = 'Nama satuan wajib diisi.'

    setErrors(validasi)

    if (Object.keys(validasi).length > 0) return

    simpanSatuan.mutate(payload)
  }

  return (
    <div className="flex flex-col gap-4">
      <div>
        <h2 className="text-lg font-semibold text-ink">Pengaturan</h2>
        <p className="text-xs text-muted">Profil akun dan master data aplikasi.</p>
      </div>

      <Card title="Profil Akun">
        <dl className="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
          <div>
            <dt className="text-xs text-muted">Nama</dt>
            <dd>{user?.name}</dd>
          </div>
          <div>
            <dt className="text-xs text-muted">Email</dt>
            <dd>{user?.email ?? '-'}</dd>
          </div>
          <div>
            <dt className="text-xs text-muted">Peran</dt>
            <dd>{user?.role?.name ?? user?.role_code}</dd>
          </div>
        </dl>
      </Card>

      <Card title="Peran Pengguna" description="Hak akses aplikasi ditentukan oleh peran berikut." bodyClassName="pt-0">
        {memuatRoles ? (
          <LoadingState />
        ) : !roles || roles.length === 0 ? (
          <EmptyState />
        ) : (
          <TableWrap>
            <Table>
              <thead>
                <tr>
                  <Th>Kode</Th>
                  <Th>Nama Peran</Th>
                </tr>
              </thead>
              <tbody>
                {roles.map((role) => (
                  <tr key={role.id}>
                    <Td className="font-medium">{role.code}</Td>
                    <Td className="text-muted">{role.name}</Td>
                  </tr>
                ))}
              </tbody>
            </Table>
          </TableWrap>
        )}
      </Card>

      <Card
        title="Satuan Pekerjaan"
        description="Master data satuan yang dipakai pada data pekerjaan."
        action={
          adminMode && (
            <Button size="sm" icon={<Plus className="size-4" />} onClick={() => bukaFormSatuan()}>
              Tambah Satuan
            </Button>
          )
        }
        bodyClassName="pt-0"
      >
        {memuatUnits ? (
          <LoadingState />
        ) : !units || units.length === 0 ? (
          <EmptyState judul="Belum ada satuan" pesan={adminMode ? 'Tambahkan satuan pekerjaan baru.' : undefined} />
        ) : (
          <TableWrap>
            <Table>
              <thead>
                <tr>
                  <Th className="w-2/5">Kode</Th>
                  <Th>Nama Satuan</Th>
                  {adminMode && (
                    <Th align="center" className="w-px">
                      Aksi
                    </Th>
                  )}
                </tr>
              </thead>
              <tbody>
                {units.map((unit) => (
                  <tr key={unit.id}>
                    <Td className="font-medium">{kodeSatuan(unit.code)}</Td>
                    <Td className="text-muted">{unit.name}</Td>
                    {adminMode && (
                      <Td align="center">
                        <div className="flex items-center justify-center gap-2 whitespace-nowrap">
                          <Button
                            variant="ghost"
                            size="sm"
                            className="bg-info-soft! text-info! hover:bg-info! hover:text-white!"
                            icon={<Pencil className="size-3.5" aria-hidden />}
                            onClick={() => bukaFormSatuan(unit)}
                            aria-label={`Ubah satuan ${unit.code}`}
                          >
                            Ubah
                          </Button>
                          <Button
                            variant="ghost"
                            size="sm"
                            className="bg-danger-soft! text-danger! hover:bg-danger! hover:text-white!"
                            icon={<Trash2 className="size-3.5" aria-hidden />}
                            onClick={() => mintaHapusSatuan(unit)}
                            aria-label={`Hapus satuan ${unit.code}`}
                          >
                            Hapus
                          </Button>
                        </div>
                      </Td>
                    )}
                  </tr>
                ))}
              </tbody>
            </Table>
          </TableWrap>
        )}
      </Card>

      <Modal
        open={formSatuan}
        onClose={() => setFormSatuan(false)}
        title={satuanDiedit ? 'Ubah Satuan Pekerjaan' : 'Tambah Satuan Pekerjaan'}
        description={
          satuanDiedit
            ? 'Perubahan langsung berlaku pada semua pekerjaan yang memakai satuan ini.'
            : 'Satuan baru langsung bisa dipilih saat menambah data pekerjaan.'
        }
        size="sm"
        footer={
          <>
            <Button variant="outline" onClick={() => setFormSatuan(false)} disabled={simpanSatuan.isPending}>
              Batal
            </Button>
            <Button form="form-satuan" type="submit" loading={simpanSatuan.isPending}>
              Simpan
            </Button>
          </>
        }
      >
        <form id="form-satuan" className="grid gap-4" onSubmit={kirimSatuan} noValidate>
          <Input
            label="Kode Satuan"
            required
            placeholder="Contoh: m3"
            maxLength={20}
            value={satuan.code}
            onChange={(event) => setSatuan({ ...satuan, code: event.target.value })}
            error={errors.code}
          />
          <Input
            label="Nama Satuan"
            required
            placeholder="Contoh: meter kubik"
            maxLength={80}
            value={satuan.name}
            onChange={(event) => setSatuan({ ...satuan, name: event.target.value })}
            error={errors.name}
          />
        </form>
      </Modal>

      <ConfirmDialog
        open={Boolean(satuanDihapus)}
        title="Hapus Satuan"
        pesan={`Satuan "${satuanDihapus ? kodeSatuan(satuanDihapus.code) : ''} - ${satuanDihapus?.name ?? ''}" akan dihapus. Lanjutkan?`}
        loading={hapusSatuan.isPending}
        onConfirm={() => satuanDihapus && hapusSatuan.mutate(satuanDihapus.id)}
        onClose={() => setSatuanDihapus(null)}
      />
    </div>
  )
}
